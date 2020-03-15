// ---------------------------------------------------------------------------
// Usage: npm run transpile
// ---------------------------------------------------------------------------

"use strict";

const fs = require ('fs')
    , log = require ('ololog')
    , ansi = require ('ansicolor').nice
    , { unCamelCase, precisionConstants, safeString } = require ('ccxt/js/base/functions.js')
    , {
        createFolderRecursively,
        overwriteFile,
        replaceInFile,
    } = require ('ccxt/build/fs.js')
    , errors = require ('ccxt/js/base/errors.js')
    , Transpiler = require ('ccxt/build/transpile.js')

// ============================================================================

class CCXTProTranspiler extends Transpiler {

    getBaseMethods () {
        return [
            'orderBook',
            'limitedOrderBook',
            'indexedOrderBook',
            'limitedIndexedOrderBook',
            'limitedCountedOrderBook',
            'countedOrderBook',
            'afterAsync',
            'afterDropped',
            'watch',
        ]
    }

    getPythonBaseMethods () {
        return this.getBaseMethods ()
    }

    getPHPBaseMethods () {
        return this.getBaseMethods ()
    }

    // getCommonRegexes () {
    //     return super.getCommonRegexes ().concat ([
    //         [ /\.orderBook\s/g, '.order_book' ],
    //         [ /\.limitedOrderBook\s/g, '.limited_order_book' ],
    //         [ /\.indexedOrderBook\s/g, '.indexed_order_book' ],
    //         [ /\.limitedIndexedOrderBook\s/g, '.limited_indexed_order_book' ],
    //         [ /\.limitedCountedOrderBook\s/g, '.limited_counted_order_book' ],
    //         [ /\.countedOrderBook\s/g, '.counted_order_book' ],
    //     ])
    // }

    getPHPPreamble () {
        return [
            "<?php",
            "namespace ccxtpro;",
            "include_once __DIR__ . '/../../vendor/autoload.php';",
            "// ----------------------------------------------------------------------------",
            "",
            "// PLEASE DO NOT EDIT THIS FILE, IT IS GENERATED AND WILL BE OVERWRITTEN:",
            "// https://github.com/ccxt/ccxt/blob/master/CONTRIBUTING.md#how-to-contribute-code",
            "",
            "// -----------------------------------------------------------------------------",
            "",
        ].join ("\n")
    }

    createPythonClassDeclaration (className, baseClass) {
        const baseClasses = (baseClass.indexOf ('ccxt.') === 0) ?
            [ 'Exchange', baseClass ] :
            [ baseClass ]
        return 'class ' + className + '(' + baseClasses.join (', ') + '):'
    }

    createPythonClassImports (baseClass, async = false) {

        const baseClasses = {
            'Exchange': 'base.exchange',
        }

        async = (async ? '.async_support' : '')

        if (baseClass.indexOf ('ccxt.') === 0) {
            return [
                'from ccxtpro.base.exchange import Exchange',
                'import ccxt' + async + ' as ccxt'
            ]
        } else {
            return [
                'from ccxtpro.' + safeString (baseClasses, baseClass, baseClass) + ' import ' + baseClass
            ]
        }
        // return [
        //     (baseClass.indexOf ('ccxt.') === 0) ?
        //         ('import ccxt' + async + ' as ccxt') :
        //         ('from ccxtpro.' + safeString (baseClasses, baseClass, baseClass) + ' import ' + baseClass)
        // ]
    }


    createPythonClassHeader (ccxtImports, bodyAsString) {
        return [
            "# -*- coding: utf-8 -*-",
            "",
            "# PLEASE DO NOT EDIT THIS FILE, IT IS GENERATED AND WILL BE OVERWRITTEN:",
            "# https://github.com/ccxt/ccxt/blob/master/CONTRIBUTING.md#how-to-contribute-code",
            "",
            // "from ccxtpro.base.exchange import Exchange",
            ... ccxtImports,
            // 'from ' + importFrom + ' import ' + baseClass,
            ... (bodyAsString.match (/basestring/) ? [
                "",
                "# -----------------------------------------------------------------------------",
                "",
                "try:",
                "    basestring  # Python 3",
                "except NameError:",
                "    basestring = str  # Python 2",
            ] : [])
        ]
    }

    createPHPClassDeclaration (className, baseClass) {
        let lines = [
            'class ' + className + ' extends ' + baseClass.replace ('ccxt.', '\\ccxt\\') + ' {',
        ]
        if (baseClass.indexOf ('ccxt.') === 0) {
            lines = lines.concat ([
                '',
                '    use ClientTrait;'
            ])
        }
        return lines.join ("\n")
    }

    createPHPClassHeader (className, baseClass, bodyAsString) {
        return [
            "<?php",
            "",
            "namespace ccxtpro;",
            "",
            "// PLEASE DO NOT EDIT THIS FILE, IT IS GENERATED AND WILL BE OVERWRITTEN:",
            "// https://github.com/ccxt/ccxt/blob/master/CONTRIBUTING.md#how-to-contribute-code",
            "",
            "use Exception; // a common import",
        ]
    }

    // ------------------------------------------------------------------------

    transpileOrderBookTest () {
        const jsFile = './js/test/base/test.OrderBook.js'
        const pyFile = './python/test/test_order_book.py'
        const phpFile = './php/test/OrderBook.php'

        log.magenta ('Transpiling from', jsFile.yellow)
        let js = fs.readFileSync (jsFile).toString ()

        js = this.regexAll (js, [
            [ /\'use strict\';?\s+/g, '' ],
            [ /[^\n]+require[^\n]+\n/g, '' ],
            [ /function equals \([\S\s]+?return true\n}\n/g, '' ],
        ])

        const options = { js, removeEmptyLines: false }
        const transpiled = this.transpileJavaScriptToPythonAndPHP (options)
        const { python3Body, python2Body, phpBody } = transpiled

        const pythonHeader = [
            "",
            "from ccxtpro.base.order_book import OrderBook, IndexedOrderBook, CountedOrderBook, IncrementalOrderBook, IncrementalIndexedOrderBook  # noqa: F402",
            "",
            "",
            "def equals(a, b):",
            "    return a == b",
            "",
        ].join ("\n")

        const phpHeader = [
            "",
            "function equals($a, $b) {",
            "    return json_encode($a) === json_encode($b);",
            "}",
        ].join ("\n")

        const python = this.getPythonPreamble () + pythonHeader + python2Body
        const php = this.getPHPPreamble () + phpHeader + phpBody

        log.magenta ('→', pyFile.yellow)
        log.magenta ('→', phpFile.yellow)

        overwriteFile (pyFile, python)
        overwriteFile (phpFile, php)
    }

    // ------------------------------------------------------------------------

    exportTypeScriptDeclarations (file, classes) {

        log.bright.cyan ('Exporting TypeScript declarations →', file.yellow)

        const regex = /\/[\n]{2}(?:    export declare class [^\s]+ extends [^\s]+ \{\}[\r]?[\n])+/
        const replacement = "/\n\n" + Object.keys (classes).map (className => {
            const baseClass = classes[className]
            return '    export declare class ' + className + ' extends ' + baseClass + " {}"
        }).join ("\n") + "\n"

        replaceInFile (file, regex, replacement)
    }

    // ------------------------------------------------------------------------

    transpileEverything () {

        // default pattern is '.js'
        const [ /* node */, /* script */, pattern ] = process.argv
            // , python2Folder = './python/ccxtpro/', // CCXT Pro does not support Python 2
            , python3Folder = './python/ccxtpro/'
            , phpFolder     = './php/'
            , options = { /* python2Folder, */ python3Folder, phpFolder }

        // createFolderRecursively (python2Folder)
        createFolderRecursively (python3Folder)
        createFolderRecursively (phpFolder)

        this.transpileOrderBookTest ()
        const classes = this.transpileDerivedExchangeFiles ('./js/', options, pattern)

        if (classes === null) {
            log.bright.yellow ('0 files transpiled.')
            return;
        }

        // HINT: if we're going to support specific class definitions
        // this process won't work anymore as it will override the definitions
        this.exportTypeScriptDeclarations ('./ccxt.pro.d.ts', classes)

        // transpileErrorHierarchy ()

        // transpilePrecisionTests ()
        // transpileDateTimeTests ()
        // transpileCryptoTests ()

        log.bright.green ('Transpiled successfully.')
    }
}

// ============================================================================
// main entry point

if (require.main === module) {

    // if called directly like `node module`
    const transpiler = new CCXTProTranspiler ()
    transpiler.transpileEverything ()

} else {

    // do nothing if required as a module
}

// ============================================================================

module.exports = {}
