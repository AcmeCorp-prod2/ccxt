<?php

namespace ccxt\async;

// PLEASE DO NOT EDIT THIS FILE, IT IS GENERATED AND WILL BE OVERWRITTEN:
// https://github.com/ccxt/ccxt/blob/master/CONTRIBUTING.md#how-to-contribute-code

use Exception; // a common import
use \ccxt\ExchangeError;
use \ccxt\Precise;

class btcbox extends Exchange {

    public function describe() {
        return $this->deep_extend(parent::describe(), array(
            'id' => 'btcbox',
            'name' => 'BtcBox',
            'countries' => array( 'JP' ),
            'rateLimit' => 1000,
            'version' => 'v1',
            'has' => array(
                'CORS' => null,
                'spot' => true,
                'margin' => false,
                'swap' => false,
                'future' => false,
                'option' => false,
                'addMargin' => false,
                'cancelOrder' => true,
                'createOrder' => true,
                'createReduceOnlyOrder' => false,
                'fetchBalance' => true,
                'fetchBorrowRate' => false,
                'fetchBorrowRateHistories' => false,
                'fetchBorrowRateHistory' => false,
                'fetchBorrowRates' => false,
                'fetchBorrowRatesPerSymbol' => false,
                'fetchFundingHistory' => false,
                'fetchFundingRate' => false,
                'fetchFundingRateHistory' => false,
                'fetchFundingRates' => false,
                'fetchIndexOHLCV' => false,
                'fetchLeverage' => false,
                'fetchMarginMode' => false,
                'fetchMarkOHLCV' => false,
                'fetchOpenInterestHistory' => false,
                'fetchOpenOrders' => true,
                'fetchOrder' => true,
                'fetchOrderBook' => true,
                'fetchOrders' => true,
                'fetchPosition' => false,
                'fetchPositionMode' => false,
                'fetchPositions' => false,
                'fetchPositionsRisk' => false,
                'fetchPremiumIndexOHLCV' => false,
                'fetchTicker' => true,
                'fetchTickers' => null,
                'fetchTrades' => true,
                'fetchTransfer' => false,
                'fetchTransfers' => false,
                'fetchWithdrawal' => false,
                'fetchWithdrawals' => false,
                'reduceMargin' => false,
                'setLeverage' => false,
                'setMarginMode' => false,
                'setPositionMode' => false,
                'transfer' => false,
                'withdraw' => false,
            ),
            'urls' => array(
                'logo' => 'https://user-images.githubusercontent.com/51840849/87327317-98c55400-c53c-11ea-9a11-81f7d951cc74.jpg',
                'api' => array(
                    'rest' => 'https://www.btcbox.co.jp/api',
                ),
                'www' => 'https://www.btcbox.co.jp/',
                'doc' => 'https://blog.btcbox.jp/en/archives/8762',
                'fees' => 'https://support.btcbox.co.jp/hc/en-us/articles/360001235694-Fees-introduction',
            ),
            'api' => array(
                'public' => array(
                    'get' => array(
                        'depth',
                        'orders',
                        'ticker',
                    ),
                ),
                'private' => array(
                    'post' => array(
                        'balance',
                        'trade_add',
                        'trade_cancel',
                        'trade_list',
                        'trade_view',
                        'wallet',
                    ),
                ),
            ),
            'markets' => array(
                'BTC/JPY' => array( 'id' => 'btc', 'symbol' => 'BTC/JPY', 'base' => 'BTC', 'quote' => 'JPY', 'baseId' => 'btc', 'quoteId' => 'jpy', 'taker' => $this->parse_number('0.0005'), 'maker' => $this->parse_number('0.0005'), 'type' => 'spot', 'spot' => true ),
                'ETH/JPY' => array( 'id' => 'eth', 'symbol' => 'ETH/JPY', 'base' => 'ETH', 'quote' => 'JPY', 'baseId' => 'eth', 'quoteId' => 'jpy', 'taker' => $this->parse_number('0.0010'), 'maker' => $this->parse_number('0.0010'), 'type' => 'spot', 'spot' => true ),
                'LTC/JPY' => array( 'id' => 'ltc', 'symbol' => 'LTC/JPY', 'base' => 'LTC', 'quote' => 'JPY', 'baseId' => 'ltc', 'quoteId' => 'jpy', 'taker' => $this->parse_number('0.0010'), 'maker' => $this->parse_number('0.0010'), 'type' => 'spot', 'spot' => true ),
                'BCH/JPY' => array( 'id' => 'bch', 'symbol' => 'BCH/JPY', 'base' => 'BCH', 'quote' => 'JPY', 'baseId' => 'bch', 'quoteId' => 'jpy', 'taker' => $this->parse_number('0.0010'), 'maker' => $this->parse_number('0.0010'), 'type' => 'spot', 'spot' => true ),
            ),
            'precisionMode' => TICK_SIZE,
            'exceptions' => array(
                '104' => '\\ccxt\\AuthenticationError',
                '105' => '\\ccxt\\PermissionDenied',
                '106' => '\\ccxt\\InvalidNonce',
                '107' => '\\ccxt\\InvalidOrder', // price should be an integer
                '200' => '\\ccxt\\InsufficientFunds',
                '201' => '\\ccxt\\InvalidOrder', // amount too small
                '202' => '\\ccxt\\InvalidOrder', // price should be [0 : 1000000]
                '203' => '\\ccxt\\OrderNotFound',
                '401' => '\\ccxt\\OrderNotFound', // cancel canceled, closed or non-existent order
                '402' => '\\ccxt\\DDoSProtection',
            ),
        ));
    }

    public function parse_balance($response) {
        $result = array( 'info' => $response );
        $codes = is_array($this->currencies) ? array_keys($this->currencies) : array();
        for ($i = 0; $i < count($codes); $i++) {
            $code = $codes[$i];
            $currency = $this->currency($code);
            $currencyId = $currency['id'];
            $free = $currencyId . '_balance';
            if (is_array($response) && array_key_exists($free, $response)) {
                $account = $this->account();
                $used = $currencyId . '_lock';
                $account['free'] = $this->safe_string($response, $free);
                $account['used'] = $this->safe_string($response, $used);
                $result[$code] = $account;
            }
        }
        return $this->safe_balance($result);
    }

    public function fetch_balance($params = array ()) {
        /**
         * query for balance and get the amount of funds available for trading or funds locked in orders
         * @param {array} $params extra parameters specific to the btcbox api endpoint
         * @return {array} a ~@link https://docs.ccxt.com/en/latest/manual.html?#balance-structure balance structure~
         */
        yield $this->load_markets();
        $response = yield $this->privatePostBalance ($params);
        return $this->parse_balance($response);
    }

    public function fetch_order_book($symbol, $limit = null, $params = array ()) {
        /**
         * fetches information on open orders with bid (buy) and ask (sell) prices, volumes and other data
         * @param {string} $symbol unified $symbol of the $market to fetch the order book for
         * @param {int|null} $limit the maximum amount of order book entries to return
         * @param {array} $params extra parameters specific to the btcbox api endpoint
         * @return {array} A dictionary of {@link https://docs.ccxt.com/en/latest/manual.html#order-book-structure order book structures} indexed by $market symbols
         */
        yield $this->load_markets();
        $market = $this->market($symbol);
        $request = array();
        $numSymbols = count($this->symbols);
        if ($numSymbols > 1) {
            $request['coin'] = $market['baseId'];
        }
        $response = yield $this->publicGetDepth (array_merge($request, $params));
        return $this->parse_order_book($response, $market['symbol']);
    }

    public function parse_ticker($ticker, $market = null) {
        $timestamp = $this->milliseconds();
        $symbol = $this->safe_symbol(null, $market);
        $last = $this->safe_string($ticker, 'last');
        return $this->safe_ticker(array(
            'symbol' => $symbol,
            'timestamp' => $timestamp,
            'datetime' => $this->iso8601($timestamp),
            'high' => $this->safe_string($ticker, 'high'),
            'low' => $this->safe_string($ticker, 'low'),
            'bid' => $this->safe_string($ticker, 'buy'),
            'bidVolume' => null,
            'ask' => $this->safe_string($ticker, 'sell'),
            'askVolume' => null,
            'vwap' => null,
            'open' => null,
            'close' => $last,
            'last' => $last,
            'previousClose' => null,
            'change' => null,
            'percentage' => null,
            'average' => null,
            'baseVolume' => $this->safe_string($ticker, 'vol'),
            'quoteVolume' => $this->safe_string($ticker, 'volume'),
            'info' => $ticker,
        ), $market);
    }

    public function fetch_ticker($symbol, $params = array ()) {
        /**
         * fetches a price ticker, a statistical calculation with the information calculated over the past 24 hours for a specific $market
         * @param {string} $symbol unified $symbol of the $market to fetch the ticker for
         * @param {array} $params extra parameters specific to the btcbox api endpoint
         * @return {array} a {@link https://docs.ccxt.com/en/latest/manual.html#ticker-structure ticker structure}
         */
        yield $this->load_markets();
        $market = $this->market($symbol);
        $request = array();
        $numSymbols = count($this->symbols);
        if ($numSymbols > 1) {
            $request['coin'] = $market['baseId'];
        }
        $response = yield $this->publicGetTicker (array_merge($request, $params));
        return $this->parse_ticker($response, $market);
    }

    public function parse_trade($trade, $market = null) {
        //
        // fetchTrades (public)
        //
        //      {
        //          "date":"0",
        //          "price":3,
        //          "amount":0.1,
        //          "tid":"1",
        //          "type":"buy"
        //      }
        //
        $timestamp = $this->safe_timestamp($trade, 'date');
        $market = $this->safe_market(null, $market);
        $id = $this->safe_string($trade, 'tid');
        $priceString = $this->safe_string($trade, 'price');
        $amountString = $this->safe_string($trade, 'amount');
        $type = null;
        $side = $this->safe_string($trade, 'type');
        return $this->safe_trade(array(
            'info' => $trade,
            'id' => $id,
            'order' => null,
            'timestamp' => $timestamp,
            'datetime' => $this->iso8601($timestamp),
            'symbol' => $market['symbol'],
            'type' => $type,
            'side' => $side,
            'takerOrMaker' => null,
            'price' => $priceString,
            'amount' => $amountString,
            'cost' => null,
            'fee' => null,
        ), $market);
    }

    public function fetch_trades($symbol, $since = null, $limit = null, $params = array ()) {
        /**
         * get the list of most recent trades for a particular $symbol
         * @param {string} $symbol unified $symbol of the $market to fetch trades for
         * @param {int|null} $since timestamp in ms of the earliest trade to fetch
         * @param {int|null} $limit the maximum amount of trades to fetch
         * @param {array} $params extra parameters specific to the btcbox api endpoint
         * @return {[array]} a list of ~@link https://docs.ccxt.com/en/latest/manual.html?#public-trades trade structures~
         */
        yield $this->load_markets();
        $market = $this->market($symbol);
        $request = array();
        $numSymbols = count($this->symbols);
        if ($numSymbols > 1) {
            $request['coin'] = $market['baseId'];
        }
        $response = yield $this->publicGetOrders (array_merge($request, $params));
        //
        //     array(
        //          array(
        //              "date":"0",
        //              "price":3,
        //              "amount":0.1,
        //              "tid":"1",
        //              "type":"buy"
        //          ),
        //     )
        //
        return $this->parse_trades($response, $market, $since, $limit);
    }

    public function create_order($symbol, $type, $side, $amount, $price = null, $params = array ()) {
        /**
         * create a trade order
         * @param {string} $symbol unified $symbol of the $market to create an order in
         * @param {string} $type 'market' or 'limit'
         * @param {string} $side 'buy' or 'sell'
         * @param {float} $amount how much of currency you want to trade in units of base currency
         * @param {float|null} $price the $price at which the order is to be fullfilled, in units of the quote currency, ignored in $market orders
         * @param {array} $params extra parameters specific to the btcbox api endpoint
         * @return {array} an {@link https://docs.ccxt.com/en/latest/manual.html#order-structure order structure}
         */
        yield $this->load_markets();
        $market = $this->market($symbol);
        $request = array(
            'amount' => $amount,
            'price' => $price,
            'type' => $side,
            'coin' => $market['baseId'],
        );
        $response = yield $this->privatePostTradeAdd (array_merge($request, $params));
        //
        //     {
        //         "result":true,
        //         "id":"11"
        //     }
        //
        return $this->parse_order($response, $market);
    }

    public function cancel_order($id, $symbol = null, $params = array ()) {
        /**
         * cancels an open order
         * @param {string} $id order $id
         * @param {string|null} $symbol unified $symbol of the $market the order was made in
         * @param {array} $params extra parameters specific to the btcbox api endpoint
         * @return {array} An {@link https://docs.ccxt.com/en/latest/manual.html#order-structure order structure}
         */
        yield $this->load_markets();
        // a special case for btcbox – default $symbol is BTC/JPY
        if ($symbol === null) {
            $symbol = 'BTC/JPY';
        }
        $market = $this->market($symbol);
        $request = array(
            'id' => $id,
            'coin' => $market['baseId'],
        );
        $response = yield $this->privatePostTradeCancel (array_merge($request, $params));
        //
        //     array("result":true, "id":"11")
        //
        return $this->parse_order($response, $market);
    }

    public function parse_order_status($status) {
        $statuses = array(
            // TODO => complete list
            'part' => 'open', // partially or not at all executed
            'all' => 'closed', // fully executed
            'cancelled' => 'canceled',
            'closed' => 'closed', // never encountered, seems to be bug in the doc
            'no' => 'closed', // not clarified in the docs...
        );
        return $this->safe_string($statuses, $status, $status);
    }

    public function parse_order($order, $market = null) {
        //
        //     {
        //         "id":11,
        //         "datetime":"2014-10-21 10:47:20",
        //         "type":"sell",
        //         "price":42000,
        //         "amount_original":1.2,
        //         "amount_outstanding":1.2,
        //         "status":"closed",
        //         "trades":array() // no clarification of trade value structure of $order endpoint
        //     }
        //
        $id = $this->safe_string($order, 'id');
        $datetimeString = $this->safe_string($order, 'datetime');
        $timestamp = null;
        if ($datetimeString !== null) {
            $timestamp = $this->parse8601($order['datetime'] . '+09:00'); // Tokyo time
        }
        $amount = $this->safe_string($order, 'amount_original');
        $remaining = $this->safe_string($order, 'amount_outstanding');
        $price = $this->safe_string($order, 'price');
        // $status is set by fetchOrder method only
        $status = $this->parse_order_status($this->safe_string($order, 'status'));
        // fetchOrders do not return $status, use heuristic
        if ($status === null) {
            if (Precise::string_equals($remaining, '0')) {
                $status = 'closed';
            }
        }
        $trades = null; // todo => $this->parse_trades($order['trades']);
        $market = $this->safe_market(null, $market);
        $side = $this->safe_string($order, 'type');
        return $this->safe_order(array(
            'id' => $id,
            'clientOrderId' => null,
            'timestamp' => $timestamp,
            'datetime' => $this->iso8601($timestamp),
            'lastTradeTimestamp' => null,
            'amount' => $amount,
            'remaining' => $remaining,
            'filled' => null,
            'side' => $side,
            'type' => null,
            'timeInForce' => null,
            'postOnly' => null,
            'status' => $status,
            'symbol' => $market['symbol'],
            'price' => $price,
            'stopPrice' => null,
            'cost' => null,
            'trades' => $trades,
            'fee' => null,
            'info' => $order,
            'average' => null,
        ), $market);
    }

    public function fetch_order($id, $symbol = null, $params = array ()) {
        /**
         * fetches information on an order made by the user
         * @param {string|null} $symbol unified $symbol of the $market the order was made in
         * @param {array} $params extra parameters specific to the btcbox api endpoint
         * @return {array} An {@link https://docs.ccxt.com/en/latest/manual.html#order-structure order structure}
         */
        yield $this->load_markets();
        // a special case for btcbox – default $symbol is BTC/JPY
        if ($symbol === null) {
            $symbol = 'BTC/JPY';
        }
        $market = $this->market($symbol);
        $request = array_merge(array(
            'id' => $id,
            'coin' => $market['baseId'],
        ), $params);
        $response = yield $this->privatePostTradeView (array_merge($request, $params));
        //
        //      {
        //          "id":11,
        //          "datetime":"2014-10-21 10:47:20",
        //          "type":"sell",
        //          "price":42000,
        //          "amount_original":1.2,
        //          "amount_outstanding":1.2,
        //          "status":"closed",
        //          "trades":array()
        //      }
        //
        return $this->parse_order($response, $market);
    }

    public function fetch_orders_by_type($type, $symbol = null, $since = null, $limit = null, $params = array ()) {
        yield $this->load_markets();
        // a special case for btcbox – default $symbol is BTC/JPY
        if ($symbol === null) {
            $symbol = 'BTC/JPY';
        }
        $market = $this->market($symbol);
        $request = array(
            'type' => $type, // 'open' or 'all'
            'coin' => $market['baseId'],
        );
        $response = yield $this->privatePostTradeList (array_merge($request, $params));
        //
        // array(
        //      array(
        //          "id":"7",
        //          "datetime":"2014-10-20 13:27:38",
        //          "type":"buy",
        //          "price":42750,
        //          "amount_original":0.235,
        //          "amount_outstanding":0.235
        //      ),
        // )
        //
        $orders = $this->parse_orders($response, $market, $since, $limit);
        // status (open/closed/canceled) is null
        // btcbox does not return status, but we know it's 'open' as we queried for open $orders
        if ($type === 'open') {
            for ($i = 0; $i < count($orders); $i++) {
                $orders[$i]['status'] = 'open';
            }
        }
        return $orders;
    }

    public function fetch_orders($symbol = null, $since = null, $limit = null, $params = array ()) {
        /**
         * fetches information on multiple orders made by the user
         * @param {string|null} $symbol unified market $symbol of the market orders were made in
         * @param {int|null} $since the earliest time in ms to fetch orders for
         * @param {int|null} $limit the maximum number of  orde structures to retrieve
         * @param {array} $params extra parameters specific to the btcbox api endpoint
         * @return {[array]} a list of {@link https://docs.ccxt.com/en/latest/manual.html#order-structure order structures}
         */
        return yield $this->fetch_orders_by_type('all', $symbol, $since, $limit, $params);
    }

    public function fetch_open_orders($symbol = null, $since = null, $limit = null, $params = array ()) {
        /**
         * fetch all unfilled currently open orders
         * @param {string|null} $symbol unified market $symbol
         * @param {int|null} $since the earliest time in ms to fetch open orders for
         * @param {int|null} $limit the maximum number of  open orders structures to retrieve
         * @param {array} $params extra parameters specific to the btcbox api endpoint
         * @return {[array]} a list of {@link https://docs.ccxt.com/en/latest/manual.html#order-structure order structures}
         */
        return yield $this->fetch_orders_by_type('open', $symbol, $since, $limit, $params);
    }

    public function nonce() {
        return $this->milliseconds();
    }

    public function sign($path, $api = 'public', $method = 'GET', $params = array (), $headers = null, $body = null) {
        $url = $this->urls['api']['rest'] . '/' . $this->version . '/' . $path;
        if ($api === 'public') {
            if ($params) {
                $url .= '?' . $this->urlencode($params);
            }
        } else {
            $this->check_required_credentials();
            $nonce = (string) $this->nonce();
            $query = array_merge(array(
                'key' => $this->apiKey,
                'nonce' => $nonce,
            ), $params);
            $request = $this->urlencode($query);
            $secret = $this->hash($this->encode($this->secret));
            $query['signature'] = $this->hmac($this->encode($request), $this->encode($secret));
            $body = $this->urlencode($query);
            $headers = array(
                'Content-Type' => 'application/x-www-form-urlencoded',
            );
        }
        return array( 'url' => $url, 'method' => $method, 'body' => $body, 'headers' => $headers );
    }

    public function handle_errors($httpCode, $reason, $url, $method, $headers, $body, $response, $requestHeaders, $requestBody) {
        if ($response === null) {
            return; // resort to defaultErrorHandler
        }
        // typical error $response => array("result":false,"code":"401")
        if ($httpCode >= 400) {
            return; // resort to defaultErrorHandler
        }
        $result = $this->safe_value($response, 'result');
        if ($result === null || $result === true) {
            return; // either public API (no error codes expected) or success
        }
        $code = $this->safe_value($response, 'code');
        $feedback = $this->id . ' ' . $body;
        $this->throw_exactly_matched_exception($this->exceptions, $code, $feedback);
        throw new ExchangeError($feedback); // unknown message
    }

    public function request($path, $api = 'public', $method = 'GET', $params = array (), $headers = null, $body = null, $config = array (), $context = array ()) {
        $response = yield $this->fetch2($path, $api, $method, $params, $headers, $body, $config, $context);
        if (gettype($response) === 'string') {
            // sometimes the exchange returns whitespace prepended to json
            $response = $this->strip($response);
            if (!$this->is_json_encoded_object($response)) {
                throw new ExchangeError($this->id . ' ' . $response);
            }
            $response = json_decode($response, $as_associative_array = true);
        }
        return $response;
    }
}
