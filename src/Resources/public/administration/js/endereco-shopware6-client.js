! function(e) {
    var t = {};

    function n(r) {
        if (t[r]) return t[r].exports;
        var o = t[r] = {
            i: r,
            l: !1,
            exports: {}
        };
        return e[r].call(o.exports, o, o.exports, n), o.l = !0, o.exports
    }
    n.m = e, n.c = t, n.d = function(e, t, r) {
        n.o(e, t) || Object.defineProperty(e, t, {
            enumerable: !0,
            get: r
        })
    }, n.r = function(e) {
        "undefined" != typeof Symbol && Symbol.toStringTag && Object.defineProperty(e, Symbol.toStringTag, {
            value: "Module"
        }), Object.defineProperty(e, "__esModule", {
            value: !0
        })
    }, n.t = function(e, t) {
        if (1 & t && (e = n(e)), 8 & t) return e;
        if (4 & t && "object" == typeof e && e && e.__esModule) return e;
        var r = Object.create(null);
        if (n.r(r), Object.defineProperty(r, "default", {
            enumerable: !0,
            value: e
        }), 2 & t && "string" != typeof e)
            for (var o in e) n.d(r, o, function(t) {
                return e[t]
            }.bind(null, o));
        return r
    }, n.n = function(e) {
        var t = e && e.__esModule ? function() {
            return e.default
        } : function() {
            return e
        };
        return n.d(t, "a", t), t
    }, n.o = function(e, t) {
        return Object.prototype.hasOwnProperty.call(e, t)
    }, n.p = "/bundles/enderecoshopware6client/", n(n.s = "/fV+")
}({
    "/fV+": function(e, t, n) {
        "use strict";
        n.r(t);
        n("SzlI");
        var r = n("8NO+"),
            o = n.n(r),
            c = Shopware,
            i = c.Component,
            u = c.Mixin;
        i.register("endereco-api-check-button", {
            template: o.a,
            props: ["label"],
            inject: ["enderecoSW6ClientAPITest"],
            mixins: [u.getByName("notification")],
            data: function() {
                return {
                    isLoading: !1,
                    isSaveSuccessful: !1
                }
            },
            computed: {
                pluginConfig: function() {
                    for (var e = this.$parent; void 0 === e.actualConfigData;) e = e.$parent;
                    return e.actualConfigData.null
                }
            },
            methods: {
                saveFinish: function() {
                    this.isSaveSuccessful = !1
                },
                check: function() {
                    var e = this;
                    this.isLoading = !0, this.enderecoSW6ClientAPITest.check(this.pluginConfig).then((function(t) {
                        t.success ? (e.isSaveSuccessful = !0, e.createNotificationSuccess({
                            title: e.$tc("endereco-api-check-button.title"),
                            message: e.$tc("endereco-api-check-button.success")
                        })) : e.createNotificationError({
                            title: e.$tc("endereco-api-check-button.title"),
                            message: e.$tc("endereco-api-check-button.error")
                        }), e.isLoading = !1
                    }))
                }
            }
        });
        var s = n("Zbfr"),
            a = n("IxMq");
        Shopware.Locale.extend("de-DE", s), Shopware.Locale.extend("en-GB", a)
    },
    "8NO+": function(e, t) {
        e.exports = '<div>\n    <sw-button-process\n            :isLoading="isLoading"\n            :processSuccess="isSaveSuccessful"\n            @process-finish="saveFinish"\n            @click="check"\n    >{{ $tc(\'endereco-api-check-button.button\') }}</sw-button-process>\n</div>\n'
    },
    IxMq: function(e) {
        e.exports = JSON.parse('{"endereco-api-check-button":{"title":"API Check","success":"Connection was successfully tested","error":"Connection could not be established. Please check the api key and the server url","button":"Test API connection"}}')
    },
    SzlI: function(e, t) {
        function n(e) {
            return (n = "function" == typeof Symbol && "symbol" == typeof Symbol.iterator ? function(e) {
                return typeof e
            } : function(e) {
                return e && "function" == typeof Symbol && e.constructor === Symbol && e !== Symbol.prototype ? "symbol" : typeof e
            })(e)
        }

        function r(e, t) {
            if (!(e instanceof t)) throw new TypeError("Cannot call a class as a function")
        }

        function o(e, t) {
            for (var n = 0; n < t.length; n++) {
                var r = t[n];
                r.enumerable = r.enumerable || !1, r.configurable = !0, "value" in r && (r.writable = !0), Object.defineProperty(e, r.key, r)
            }
        }

        function c(e, t) {
            return (c = Object.setPrototypeOf || function(e, t) {
                return e.__proto__ = t, e
            })(e, t)
        }

        function i(e) {
            var t = function() {
                if ("undefined" == typeof Reflect || !Reflect.construct) return !1;
                if (Reflect.construct.sham) return !1;
                if ("function" == typeof Proxy) return !0;
                try {
                    return Boolean.prototype.valueOf.call(Reflect.construct(Boolean, [], (function() {}))), !0
                } catch (e) {
                    return !1
                }
            }();
            return function() {
                var n, r = s(e);
                if (t) {
                    var o = s(this).constructor;
                    n = Reflect.construct(r, arguments, o)
                } else n = r.apply(this, arguments);
                return u(this, n)
            }
        }

        function u(e, t) {
            if (t && ("object" === n(t) || "function" == typeof t)) return t;
            if (void 0 !== t) throw new TypeError("Derived constructors may only return object or undefined");
            return function(e) {
                if (void 0 === e) throw new ReferenceError("this hasn't been initialised - super() hasn't been called");
                return e
            }(e)
        }

        function s(e) {
            return (s = Object.setPrototypeOf ? Object.getPrototypeOf : function(e) {
                return e.__proto__ || Object.getPrototypeOf(e)
            })(e)
        }
        var a = Shopware.Classes.ApiService,
            f = Shopware.Application,
            l = function(e) {
                ! function(e, t) {
                    if ("function" != typeof t && null !== t) throw new TypeError("Super expression must either be null or a function");
                    e.prototype = Object.create(t && t.prototype, {
                        constructor: {
                            value: e,
                            writable: !0,
                            configurable: !0
                        }
                    }), Object.defineProperty(e, "prototype", {
                        writable: !1
                    }), t && c(e, t)
                }(f, e);
                var t, n, u, s = i(f);

                function f(e, t) {
                    var n = arguments.length > 2 && void 0 !== arguments[2] ? arguments[2] : "endereco-shopware6-client";
                    return r(this, f), s.call(this, e, t, n)
                }
                return t = f, (n = [{
                    key: "check",
                    value: function(e) {
                        var t = this.getBasicHeaders({});
                        return this.httpClient.post("_action/".concat(this.getApiBasePath(), "/verify"), e, {
                            headers: t
                        }).then((function(e) {
                            return a.handleResponse(e)
                        }))
                    }
                }]) && o(t.prototype, n), u && o(t, u), Object.defineProperty(t, "prototype", {
                    writable: !1
                }), f
            }(a);
        f.addServiceProvider("enderecoSW6ClientAPITest", (function(e) {
            var t = f.getContainer("init");
            return new l(t.httpClient, e.loginService)
        }))
    },
    Zbfr: function(e) {
        e.exports = JSON.parse('{"endereco-api-check-button":{"title":"API Prüfung","success":"Verbindung wurde erfolgreich geprüft","error":"Verbindung konnte nicht hergestellt werden. Bitte prüfe den API Key und die Server URL","button":"Verbindung zum Endereco Server prüfen"}}')
    }
});