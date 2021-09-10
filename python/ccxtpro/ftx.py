# -*- coding: utf-8 -*-

# PLEASE DO NOT EDIT THIS FILE, IT IS GENERATED AND WILL BE OVERWRITTEN:
# https://github.com/ccxt/ccxt/blob/master/CONTRIBUTING.md#how-to-contribute-code

from ccxtpro.base.exchange import Exchange
import ccxt.async_support as ccxt
from ccxtpro.base.cache import ArrayCache, ArrayCacheBySymbolById
import hashlib
from ccxt.base.errors import ExchangeError
from ccxt.base.errors import AuthenticationError
from ccxt.base.errors import ExchangeNotAvailable


class ftx(Exchange, ccxt.ftx):

    def describe(self):
        return self.deep_extend(super(ftx, self).describe(), {
            'has': {
                'ws': True,
                'watchOrderBook': True,
                'watchTicker': True,
                'watchTrades': True,
                'watchOHLCV': False,  # missing on the exchange side
                'watchBalance': False,  # missing on the exchange side
                'watchOrders': True,
                'watchMyTrades': True,
            },
            'urls': {
                'api': {
                    'ws': 'wss://{hostname}/ws',
                },
            },
            'options': {
                'ordersLimit': 1000,
                'tradesLimit': 1000,
            },
            'streaming': {
                # ftx does not support built-in ws protocol-level ping-pong
                # instead it requires a custom text-based ping-pong
                'ping': self.ping,
                'keepAlive': 15000,
            },
            'exceptions': {
                'exact': {
                    'Internal server error': ExchangeNotAvailable,
                    'Invalid login credentials': AuthenticationError,
                    'Not logged in': AuthenticationError,
                },
            },
        })

    async def watch_public(self, symbol, channel, params={}):
        await self.load_markets()
        market = self.market(symbol)
        marketId = market['id']
        url = self.implode_params(self.urls['api']['ws'], {'hostname': self.hostname})
        request = {
            'op': 'subscribe',
            'channel': channel,
            'market': marketId,
        }
        messageHash = channel + ':' + marketId
        return await self.watch(url, messageHash, request, messageHash)

    async def watch_private(self, channel, symbol=None, params={}):
        await self.load_markets()
        messageHash = channel
        if symbol is not None:
            market = self.market(symbol)
            messageHash = messageHash + ':' + market['id']
        await self.authenticate()
        url = self.implode_params(self.urls['api']['ws'], {'hostname': self.hostname})
        request = {
            'op': 'subscribe',
            'channel': channel,
        }
        return await self.watch(url, messageHash, request, channel)

    def authenticate(self, params={}):
        url = self.implode_params(self.urls['api']['ws'], {'hostname': self.hostname})
        client = self.client(url)
        authenticate = 'authenticate'
        method = 'login'
        if not (authenticate in client.subscriptions):
            self.check_required_credentials()
            client.subscriptions[authenticate] = True
            time = self.milliseconds()
            payload = str(time) + 'websocket_login'
            signature = self.hmac(self.encode(payload), self.encode(self.secret), hashlib.sha256, 'hex')
            messageArgs = {
                'key': self.apiKey,
                'time': time,
                'sign': signature,
            }
            options = self.safe_value(self.options, 'sign', {})
            headerPrefix = self.safe_string(options, self.hostname, 'FTX')
            subaccount = self.safe_string(self.headers, headerPrefix + '-SUBACCOUNT')
            if subaccount is not None:
                messageArgs['subaccount'] = subaccount
            message = {
                'args': messageArgs,
                'op': method,
            }
            # ftx does not reply to self message
            future = self.watch(url, method, message)
            future.resolve(True)
        return client.future(method)

    async def watch_ticker(self, symbol, params={}):
        return await self.watch_public(symbol, 'ticker')

    async def watch_trades(self, symbol, since=None, limit=None, params={}):
        trades = await self.watch_public(symbol, 'trades')
        if self.newUpdates:
            limit = trades.getLimit(symbol, limit)
        return self.filter_by_since_limit(trades, since, limit, 'timestamp', True)

    async def watch_order_book(self, symbol, limit=None, params={}):
        orderbook = await self.watch_public(symbol, 'orderbook')
        return orderbook.limit(limit)

    def handle_partial(self, client, message):
        methods = {
            'orderbook': self.handle_order_book_snapshot,
        }
        methodName = self.safe_string(message, 'channel')
        method = self.safe_value(methods, methodName)
        if method:
            method(client, message)

    def handle_update(self, client, message):
        methods = {
            'trades': self.handle_trade,
            'ticker': self.handle_ticker,
            'orderbook': self.handle_order_book_update,
            'orders': self.handle_order,
            'fills': self.handle_my_trade,
        }
        methodName = self.safe_string(message, 'channel')
        method = self.safe_value(methods, methodName)
        if method:
            method(client, message)

    def handle_message(self, client, message):
        methods = {
            # ftx API docs say that all tickers and trades will be "partial"
            # however, in fact those are "update"
            # therefore we don't need to parse the "partial" update
            # since it is only used for orderbooks...
            # uncomment to fix if self is wrong
            # 'partial': self.handle_partial,
            'partial': self.handle_order_book_snapshot,
            'update': self.handle_update,
            'subscribed': self.handle_subscription_status,
            'unsubscribed': self.handle_unsubscription_status,
            'info': self.handle_info,
            'error': self.handle_error,
            'pong': self.handle_pong,
        }
        methodName = self.safe_string(message, 'type')
        method = self.safe_value(methods, methodName)
        if method:
            method(client, message)

    def get_message_hash(self, message):
        channel = self.safe_string(message, 'channel')
        marketId = self.safe_string(message, 'market')
        return channel + ':' + marketId

    def handle_ticker(self, client, message):
        #
        #     {
        #         channel: 'ticker',
        #         market: 'BTC/USD',
        #         type: 'update',
        #         data: {
        #             bid: 6652,
        #             ask: 6653,
        #             bidSize: 17.6608,
        #             askSize: 18.1869,
        #             last: 6655,
        #             time: 1585787827.3118029
        #         }
        #     }
        #
        data = self.safe_value(message, 'data', {})
        marketId = self.safe_string(message, 'market')
        if marketId in self.markets_by_id:
            market = self.markets_by_id[marketId]
            ticker = self.parse_ticker(data, market)
            symbol = ticker['symbol']
            self.tickers[symbol] = ticker
            messageHash = self.get_message_hash(message)
            client.resolve(ticker, messageHash)
        return message

    def handle_order_book_snapshot(self, client, message):
        #
        #     {
        #         channel: "orderbook",
        #         market: "BTC/USD",
        #         type: "partial",
        #         data: {
        #             time: 1585812237.6300597,
        #             checksum: 2028058404,
        #             bids: [
        #                 [6655.5, 21.23],
        #                 [6655, 41.0165],
        #                 [6652.5, 15.1985],
        #             ],
        #             asks: [
        #                 [6658, 48.8094],
        #                 [6659.5, 15.6184],
        #                 [6660, 16.7178],
        #             ],
        #             action: "partial"
        #         }
        #     }
        #
        data = self.safe_value(message, 'data', {})
        marketId = self.safe_string(message, 'market')
        if marketId in self.markets_by_id:
            market = self.markets_by_id[marketId]
            symbol = market['symbol']
            options = self.safe_value(self.options, 'watchOrderBook', {})
            limit = self.safe_integer(options, 'limit', 400)
            orderbook = self.order_book({}, limit)
            self.orderbooks[symbol] = orderbook
            timestamp = self.safe_timestamp(data, 'time')
            snapshot = self.parse_order_book(data, symbol, timestamp)
            orderbook.reset(snapshot)
            # checksum = self.safe_string(data, 'checksum')
            # todo: self.checkOrderBookChecksum(client, orderbook, checksum)
            self.orderbooks[symbol] = orderbook
            messageHash = self.get_message_hash(message)
            client.resolve(orderbook, messageHash)

    def handle_delta(self, bookside, delta):
        price = self.safe_float(delta, 0)
        amount = self.safe_float(delta, 1)
        bookside.store(price, amount)

    def handle_deltas(self, bookside, deltas):
        for i in range(0, len(deltas)):
            self.handle_delta(bookside, deltas[i])

    def handle_order_book_update(self, client, message):
        #
        #     {
        #         channel: "orderbook",
        #         market: "BTC/USD",
        #         type: "update",
        #         data: {
        #             time: 1585812417.4673214,
        #             checksum: 2215307596,
        #             bids: [[6668, 21.4066], [6669, 25.8738], [4498, 0]],
        #             asks: [],
        #             action: "update"
        #         }
        #     }
        #
        data = self.safe_value(message, 'data', {})
        marketId = self.safe_string(message, 'market')
        if marketId in self.markets_by_id:
            market = self.markets_by_id[marketId]
            symbol = market['symbol']
            orderbook = self.orderbooks[symbol]
            self.handle_deltas(orderbook['asks'], self.safe_value(data, 'asks', []))
            self.handle_deltas(orderbook['bids'], self.safe_value(data, 'bids', []))
            # orderbook['nonce'] = u
            timestamp = self.safe_timestamp(data, 'time')
            orderbook['timestamp'] = timestamp
            orderbook['datetime'] = self.iso8601(timestamp)
            # checksum = self.safe_string(data, 'checksum')
            # todo: self.checkOrderBookChecksum(client, orderbook, checksum)
            self.orderbooks[symbol] = orderbook
            messageHash = self.get_message_hash(message)
            client.resolve(orderbook, messageHash)

    def handle_trade(self, client, message):
        #
        #     {
        #         channel:   "trades",
        #         market:   "BTC-PERP",
        #         type:   "update",
        #         data: [
        #             {
        #                 id:  33517246,
        #                 price:  6661.5,
        #                 size:  2.3137,
        #                 side: "sell",
        #                 liquidation:  False,
        #                 time: "2020-04-02T07:45:12.011352+00:00"
        #             }
        #         ]
        #     }
        #
        data = self.safe_value(message, 'data', {})
        marketId = self.safe_string(message, 'market')
        if marketId in self.markets_by_id:
            market = self.markets_by_id[marketId]
            symbol = market['symbol']
            messageHash = self.get_message_hash(message)
            tradesLimit = self.safe_integer(self.options, 'tradesLimit', 1000)
            stored = self.safe_value(self.trades, symbol)
            if stored is None:
                stored = ArrayCache(tradesLimit)
                self.trades[symbol] = stored
            if isinstance(data, list):
                trades = self.parse_trades(data, market)
                for i in range(0, len(trades)):
                    stored.append(trades[i])
            else:
                trade = self.parse_trade(message, market)
                stored.append(trade)
            client.resolve(stored, messageHash)
        return message

    def handle_subscription_status(self, client, message):
        # todo: handle unsubscription status
        # {'type': 'subscribed', 'channel': 'trades', 'market': 'BTC-PERP'}
        return message

    def handle_unsubscription_status(self, client, message):
        # todo: handle unsubscription status
        # {'type': 'unsubscribed', 'channel': 'trades', 'market': 'BTC-PERP'}
        return message

    def handle_info(self, client, message):
        # todo: handle info messages
        # Used to convey information to the user. Is accompanied by a code and msg field.
        # When our servers restart, you may see an info message with code 20001. If you do, please reconnect.
        return message

    def handle_error(self, client, message):
        errorMessage = self.safe_string(message, 'msg')
        Exception = self.safe_value(self.exceptions['exact'], errorMessage)
        if Exception is None:
            error = ExchangeError(errorMessage)
            client.reject(error)
        else:
            if isinstance(Exception, AuthenticationError):
                method = 'authenticate'
                if method in client.subscriptions:
                    del client.subscriptions[method]
            error = Exception(errorMessage)
            # just reject the private api futures
            client.reject(error, 'fills')
            client.reject(error, 'orders')
        return message

    def ping(self, client):
        # ftx does not support built-in ws protocol-level ping-pong
        # instead it requires a custom json-based text ping-pong
        # https://docs.ftx.com/#websocket-api
        return {
            'op': 'ping',
        }

    def handle_pong(self, client, message):
        client.lastPong = self.milliseconds()
        return message

    async def watch_orders(self, symbol=None, since=None, limit=None, params={}):
        await self.load_markets()
        orders = await self.watch_private('orders', symbol)
        if self.newUpdates:
            limit = orders.getLimit(symbol, limit)
        return self.filter_by_symbol_since_limit(orders, symbol, since, limit, True)

    def handle_order(self, client, message):
        #
        # futures
        #
        #     {
        #         channel: 'orders',
        #         type: 'update',
        #         data: {
        #             id: 8047498974,
        #             clientId: null,
        #             market: 'ETH-PERP',
        #             type: 'limit',
        #             side: 'buy',
        #             price: 300,
        #             size: 0.1,
        #             status: 'closed',
        #             filledSize: 0,
        #             remainingSize: 0,
        #             reduceOnly: False,
        #             liquidation: False,
        #             avgFillPrice: null,
        #             postOnly: False,
        #             ioc: False,
        #             createdAt: '2020-08-22T14:35:07.861545+00:00'
        #         }
        #     }
        #
        # spot
        #
        #     {
        #         channel: 'orders',
        #         type: 'update',
        #         data: {
        #             id: 8048834542,
        #             clientId: null,
        #             market: 'ETH/USD',
        #             type: 'limit',
        #             side: 'buy',
        #             price: 300,
        #             size: 0.1,
        #             status: 'new',
        #             filledSize: 0,
        #             remainingSize: 0.1,
        #             reduceOnly: False,
        #             liquidation: False,
        #             avgFillPrice: null,
        #             postOnly: False,
        #             ioc: False,
        #             createdAt: '2020-08-22T15:17:32.184123+00:00'
        #         }
        #     }
        #
        messageHash = self.safe_string(message, 'channel')
        data = self.safe_value(message, 'data')
        order = self.parse_order(data)
        market = self.market(order['symbol'])
        if self.orders is None:
            limit = self.safe_integer(self.options, 'ordersLimit', 1000)
            self.orders = ArrayCacheBySymbolById(limit)
        orders = self.orders
        orders.append(order)
        client.resolve(orders, messageHash)
        symbolMessageHash = messageHash + ':' + market['id']
        client.resolve(orders, symbolMessageHash)

    async def watch_my_trades(self, symbol=None, since=None, limit=None, params={}):
        await self.load_markets()
        trades = await self.watch_private('fills', symbol)
        if self.newUpdates:
            limit = trades.getLimit(symbol, limit)
        return self.filter_by_symbol_since_limit(trades, symbol, since, limit, True)

    def handle_my_trade(self, client, message):
        #
        # future
        #
        #     {
        #         "channel": "fills",
        #         "type": "update"
        #         "data": {
        #             "fee": 78.05799225,
        #             "feeRate": 0.0014,
        #             "future": "BTC-PERP",
        #             "id": 7828307,
        #             "liquidity": "taker",
        #             "market": "BTC-PERP",
        #             "orderId": 38065410,
        #             "price": 3723.75,
        #             "side": "buy",
        #             "size": 14.973,
        #             "time": "2019-05-07T16:40:58.358438+00:00",
        #             "tradeId": 19129310,
        #             "type": "order"
        #         },
        #     }
        #
        # spot
        #
        #     {
        #         channel: 'fills',
        #         type: 'update',
        #         data: {
        #             baseCurrency: 'ETH',
        #             quoteCurrency: 'USD',
        #             feeCurrency: 'USD',
        #             fee: 0.0023439654,
        #             feeRate: 0.000665,
        #             future: null,
        #             id: 182349460,
        #             liquidity: 'taker'
        #             market: 'ETH/USD',
        #             orderId: 8049570214,
        #             price: 391.64,
        #             side: 'sell',
        #             size: 0.009,
        #             time: '2020-08-22T15:42:42.646980+00:00',
        #             tradeId: 90614141,
        #             type: 'order',
        #         }
        #     }
        #
        messageHash = self.safe_string(message, 'channel')
        data = self.safe_value(message, 'data', {})
        trade = self.parse_trade(data)
        market = self.market(trade['symbol'])
        if self.myTrades is None:
            limit = self.safe_integer(self.options, 'tradesLimit', 1000)
            self.myTrades = ArrayCacheBySymbolById(limit)
        tradesCache = self.myTrades
        tradesCache.append(trade)
        client.resolve(tradesCache, messageHash)
        symbolMessageHash = messageHash + ':' + market['id']
        client.resolve(tradesCache, symbolMessageHash)
