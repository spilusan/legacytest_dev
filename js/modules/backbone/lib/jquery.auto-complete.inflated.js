/**
 * Autocomplete JS of unknown origins v1.1
 */
    (function($, undefined) {
        $.fn.autoComplete = function() {
            var args = Slice.call(arguments),
            self = this,
            first = args.shift(),
            isMethod = (typeof first === 'string');
            if (isMethod) first = first.replace('.', '-');
            args = first === 'button-supply' || first === 'direct-supply' ? $.isArray(args[0]) && $.isArray(args[0][0]) ? args[0] : args: args[1] === undefined && $.isArray(args[0]) ? args[0] : args;
            return isMethod ? $(self)[first === 'option' && args.length < 2 ? 'triggerHandler': 'trigger']('autoComplete.' + first, args) : first && first[$.expando] ? $(self).trigger(first, args) : AutoCompleteFunction.call(self, first);
        };
        $.fn.bgiframe = $.fn.bgiframe ? $.fn.bgiframe: $.fn.bgIframe ? $.fn.bgIframe: function() {
            return this;
        };
        $.expando = $.expando !== undefined ? $.expando: (function() {
            var event = $.Event('keyup'),
            i;
            for (i in event)
            if (i.indexOf('jQuery') === 0)
            return i;
            return 'jQuery' + event.timeStamp;
        })();
        function now() {
            return (new Date).getTime();
        }
        var
        TRUE = true,
        FALSE = false,
        Slice = Array.prototype.slice,
        AutoComplete = $.autoComplete = {
            counter: 0,
            length: 0,
            stack: {},
            order: [],
            hasFocus: FALSE,
            getFocus: function() {
                return this.order[0] ? this.stack[this.order[0]] : undefined;
            },
            getPrevious: function() {
                for (var i = 1, l = this.order.length; i < l; i++)
                if (this.order[i])
                return this.stack[this.order[i]];
                return undefined;
            },
            remove: function(i) {
                for (var k = 0, l = this.order.length; k < l; k++)
                if (this.order[k] === i)
                this.order[k] = undefined;
                this.stack[i] = undefined;
                this.length--;
                delete this.stack[i];
            },
            getAll: function() {
                for (var i = 0, l = this.counter, stack = []; i < l; i++)
                if (this.stack[i])
                stack.push(this.stack[i]);
                return $(stack);
            },
            defaults: {
                backwardsCompatible: FALSE,
                ajax: 'ajax.php',
                ajaxCache: $.ajaxSettings.cache,
                dataSupply: [],
                dataFn: undefined,
                dataName: 'ac-data',
                list: 'auto-complete-list',
                rollover: 'auto-complete-list-rollover',
                width: undefined,
                striped: undefined,
                maxHeight: undefined,
                newList: FALSE,
                postVar: 'value',
                postData: {},
                minChars: 1,
                maxItems: -1,
                maxRequests: 0,
                requestType: 'POST',
                inputControl: undefined,
                autoFill: FALSE,
                nonInput: undefined,
                multiple: FALSE,
                multipleSeparator: ' ',
                onBlur: undefined,
                onFocus: undefined,
                onHide: undefined,
                onLoad: undefined,
                onMaxRequest: function() {},
                onRollover: undefined,
                onSelect: undefined,
                onShow: undefined,
                onSubmit: function() {
                    return TRUE;
                },
                spinner: undefined,
                preventEnterSubmit: TRUE,
                delay: 0,
                useCache: TRUE,
                cacheLimit: 50,
                leftAdjustment: -12,
                tabToNext: undefined
            }
        },
        AutoCompleteFunction = function(options) {
            return this.each(function() {
                var
                self = this,
                $input = $(self).attr('autocomplete', 'off'),
                Active = TRUE,
                LastEvent = {},
                inputval = '',
                $elems = {
                    length: 0
                },
                $li,
                view,
                ulHeight,
                liHeight,
                liPerView,
                ulOpen = FALSE,
                timeid,
                xhr,
                liFocus = -1,
                liData,
                separator,
                inputIndex = (function() {
                    AutoComplete.length++;
                    return++AutoComplete.counter;
                })(),
                requests = 0,
                cache = {
                    length: 0,
                    val: undefined,
                    list: {}
                },
                settings = $.extend({
                    width: $input.outerWidth()
                },
                AutoComplete.defaults, options || {},
                $.metadata ? $input.metadata() : {}),
                $ul = !settings.newList && $('ul.' + settings.list)[0] ? $('ul.' + settings.list).eq(0).bgiframe().data('autoComplete', TRUE) : $('<ul/>').appendTo('body').addClass(settings.list).bgiframe().hide().data('ac-selfmade', TRUE).data('autoComplete', TRUE),
                $doc = $(document).bind('click.autoComplete-' + inputIndex,
                function(event) {
                    var $elem;
                    if (Active && ulOpen && (!LastEvent || event.timeStamp - LastEvent.timeStamp > 200) && ($elem = $(event.target)).closest('ul').data('ac-input-index') !== inputIndex && $elem.data('ac-input-index') !== inputIndex) {
                        $ul.hide(event);
                        $input.blur();
                    }
                    LastEvent = event;
                });
                newUl();
                settings.requestType = settings.requestType.toUpperCase();
                separator = settings.multiple ? settings.multipleSeparator: undefined;
                AutoComplete.stack[inputIndex] = self;
                $input.data('autoComplete', TRUE).data('ac-input-index', inputIndex).data('ac-active', Active).data('ac-initial-settings', $.extend(TRUE, {},
                settings)).data('ac-settings', settings).bind(window.opera ? 'keypress.autoComplete': 'keydown.autoComplete',
                function(event) {
                    if (!Active) return TRUE;
                    var key = (LastEvent = event).keyCode,
                    enter = FALSE;
                    if (key == 9 && ulOpen) {
                        select(event);
                        if (settings.tabToNext) {
                            $(settings.tabToNext).focus();
                        }
                    }
                    else if (key == 13 && $li) {
                        enter = settings.preventEnterSubmit && ulOpen ? FALSE: TRUE;
                        select(event);
                    }
                    else if (key == 38) {
                        if (liFocus > 0) {
                            liFocus--;
                            up(event);
                        } else {
                            liFocus = -1;
                            $input.val(inputval);
                            $ul.hide(event);
                        }
                    }
                    else if (key == 40) {
                        if (liFocus < $elems.length - 1) {
                            liFocus++;
                            down(event);
                        }
                    }
                    else if (key == 33) {
                        if (liFocus > 0) {
                            liFocus -= liPerView;
                            if (liFocus < 0) liFocus = 0;
                            up(event);
                        }
                    }
                    else if (key == 34) {
                        if (liFocus < $elems.length - 1) {
                            liFocus += liPerView;
                            if (liFocus > $elems.length - 1) liFocus = $elems.length - 1;
                            down(event);
                        }
                    }
                    else if (settings.nonInput && $.inArray(key, settings.nonInput)) {
                        $ul.html('').hide(event);
                    }
                    else {
                        return TRUE;
                    }
                    LastEvent[$.expando + '_autoComplete_keydown'] = TRUE;
                    return enter;
                }).bind('keyup.autoComplete',
                function(event) {
                    if (!Active || LastEvent[$.expando + '_autoComplete_keydown']) return TRUE;
                    inputval = $input.val();
                    var key = (LastEvent = event).keyCode,
                    val = separator ? inputval.split(separator).pop() : inputval;
                    if (key != 13) {
                        cache.val = settings.inputControl === undefined ? val: settings.inputControl.apply(self, settings.backwardsCompatible ? [val, key, $ul, event] : [event, {
                            val: val,
                            key: key,
                            ul: $ul
                        }]);
                        if (cache.val.length >= settings.minChars)
                        sendRequest(event, settings, cache, (key == 8 || key == 32));
                        else if (key == 8)
                        $ul.html('').hide(event);
                    }
                }).bind('blur.autoComplete',
                function(event) {
                    if (!Active || ulOpen) return TRUE;
                    LastEvent = event;
                    $input.data('ac-hasFocus', FALSE);
                    liFocus = -1;
                    if (AutoComplete.order[0] !== undefined)
                    AutoComplete.order.unshift(undefined);
                    AutoComplete.hasFocus = FALSE;
                    $ul.hide(event);
                    if (settings.onBlur) settings.onBlur.apply(self, settings.backwardsCompatible ? [inputval, $ul, event] : [event, {
                        val: inputval,
                        ul: $ul
                    }]);
                }).bind('focus.autoComplete',
                function(event, flag) {
                    if (!Active || (AutoComplete.focus === inputIndex && flag === $.expando + '_autoComplete') || LastEvent[$.expando + '_autoComplete_enter'])
                    return TRUE;
                    LastEvent = event;
                    if (inputIndex != $ul.data('ac-input-index'))
                    $ul.html('').hide(event);
                    $input.data('ac-hasFocus', TRUE);
                    if (AutoComplete.order[0] === undefined) {
                        if (AutoComplete.order[1] === inputIndex)
                        AutoComplete.order.shift();
                        else
                        AutoComplete.order[0] = inputIndex;
                    }
                    else if (AutoComplete.order[0] != inputIndex && AutoComplete.order[1] != inputIndex)
                    AutoComplete.order.unshift(inputIndex);
                    if (AutoComplete.order.length > AutoComplete.defaults.cacheLimit)
                    AutoComplete.order.pop();
                    AutoComplete.hasFocus = TRUE;
                    if (settings.onFocus) settings.onFocus.apply(self, settings.backwardsCompatible ? [$ul, event] : [event, {
                        ul: $ul
                    }]);
                }).bind('autoComplete.settings',
                function(event, newSettings) {
                    if (!Active) return TRUE;
                    if ($.isFunction(newSettings)) {
                        var ret = newSettings.apply(self, settings.backwardsCompatible ? [settings, cache, $ul, event] : [event, {
                            settings: settings,
                            cache: cache,
                            ul: $ul
                        }]);
                        if ($.isArray(ret) && ret[0] !== undefined) {
                            settings = $.extend(TRUE, {},
                            settings, ret[0] || settings);
                            cache = $.extend(TRUE, {},
                            cache, ret[1] || cache);
                        }
                    } else {
                        settings = $.extend(TRUE, {},
                        settings, newSettings || {});
                    }
                    settings.requestType = settings.requestType.toUpperCase();
                    separator = settings.multiple ? settings.multipleSeparator: undefined;
                    $input.data('ac-settings', settings);
                    $ul = !settings.newList && $ul.hasClass(settings.list) ? $ul: !settings.newList && $('ul.' + settings.list)[0] ? $('ul.' + settings.list).bgiframe().data('autoComplete', TRUE) : $('<ul/>').appendTo('body').addClass(settings.list).bgiframe().hide().data('ac-selfmade', TRUE).data('autoComplete', TRUE);
                    newUl();
                    return LastEvent = event;
                }).bind('autoComplete.flush',
                function(event, cacheOnly) {
                    if (!Active) return TRUE;
                    cache = {
                        length: 0,
                        val: undefined,
                        list: {}
                    };
                    if (!cacheOnly) requests = 0;
                    return LastEvent = event;
                }).bind('autoComplete.button-ajax',
                function(event, postData, cacheName) {
                    if (!Active) return TRUE;
                    LastEvent = event;
                    $input.trigger('focus', [$.expando + '_autoComplete']);
                    if (typeof postData === 'string') {
                        cacheName = postData;
                        postData = {};
                    }
                    cache.val = cacheName || 'NON_404_<>!@$^&';
                    return sendRequest(event, $.extend(TRUE, {},
                    settings, {
                        maxItems: -1,
                        postData: postData || {}
                    }), cache);
                }).bind('autoComplete.button-supply',
                function(event, data, cacheName) {
                    if (!Active) return TRUE;
                    LastEvent = event;
                    $input.trigger('focus', [$.expando + '_autoComplete']);
                    if (typeof data === 'string') {
                        cacheName = data;
                        data = undefined;
                    }
                    cache.val = cacheName || 'NON_404_SUPPLY_<>!@$^&';
                    data = $.isArray(data) && data.length ? data: settings.dataSupply;
                    return sendRequest(event, $.extend(TRUE, {},
                    settings, {
                        maxItems: -1,
                        dataSupply: data,
                        dataFn: function() {
                            return TRUE;
                        }
                    }), cache);
                }).bind('autoComplete.direct-supply',
                function(event, data, cacheName) {
                    if (!Active) return TRUE;
                    LastEvent = event;
                    $input.trigger('focus', [$.expando + '_autoComplete']);
                    if (typeof data === 'string') {
                        cacheName = data;
                        data = undefined;
                    }
                    cache.val = cacheName || 'NON_404_SUPPLY_<>!@$^&';
                    data = $.isArray(data) && data.length ? data: settings.dataSupply;
                    return loadResults(event, data, $.extend(TRUE, {},
                    settings, {
                        maxItems: -1,
                        dataSupply: data,
                        dataFn: function() {
                            return TRUE;
                        }
                    }), cache);
                }).bind('autoComplete.search',
                function(event, value) {
                    if (!Active) return TRUE;
                    cache.val = value || '';
                    return sendRequest(LastEvent = event, settings, cache);
                }).bind('autoComplete.option',
                function(event) {
                    if (!Active) return TRUE;
                    LastEvent = event;
                    var args = Slice.call(arguments),
                    length = args.length;
                    return length == 3 ? (function() {
                        settings[args[1]] = args[2];
                        $input.data('ac-settings', settings);
                        return args[2];
                    })() : length == 2 ? (function() {
                        switch (args[1]) {
                        case 'ul':
                            return $ul;
                        case 'cache':
                            return cache;
                        case 'xhr':
                            return xhr;
                        case 'input':
                            return $input;
                        default:
                            return settings[args[1]] || undefined;
                        }
                    })() : settings;
                }).bind('autoComplete.enable',
                function(event) {
                    $input.data('ac-active', Active = TRUE);
                    return LastEvent = event;
                }).bind('autoComplete.disable',
                function(event) {
                    $input.data('ac-active', Active = FALSE);
                    $ul.html('').hide(event);
                    return LastEvent = event;
                }).bind('autoComplete.destroy',
                function(event) {
                    $input.removeData('autoComplete').removeData('ac-input-index').removeData('ac-initial-settings').removeData('ac-settings').removeData('ac-active').unbind('.autoComplete').unbind('autoComplete.' + ['settings', 'flush', 'button-ajax', 'button-supply', 'direct-supply', 'search', 'option', 'enable', 'disable', 'destroy'].join(' autoComplete.')).parents('form').eq(0).unbind('submit.autoComplete-' + inputIndex);
                    $doc.unbind('click.autoComplete-' + inputIndex);
                    AutoComplete.remove(inputIndex);
                    Active = FALSE;
                    var list = $ul.html('').hide(event).data('ac-inputs'),
                    i;
                    list[inputIndex] = undefined;
                    for (i in list)
                    if (list[i] === TRUE)
                    return LastEvent = event;
                    if ($ul.data('ac-selfmade') === TRUE) $ul.remove();
                    return LastEvent = event;
                }).parents('form').eq(0).bind('submit.autoComplete-' + inputIndex,
                function(event) {
                    if (!Active) return TRUE;
                    var flag = LastEvent[$.expando + '_autoComplete_enter'] || FALSE;
                    LastEvent = event;
                    return settings.preventEnterSubmit ? ((ulOpen || flag) && liFocus >= 0) ? FALSE: settings.onSubmit.call(self, event, {
                        form: this,
                        ul: $ul
                    }) : settings.onSubmit.call(self, event, {
                        form: this,
                        ul: $ul
                    });
                });
                function sendRequest(event, settings, cache, backSpace, timeout) {
                    if (settings.spinner) settings.spinner.call(self, event, {
                        active: TRUE,
                        ul: $ul
                    });
                    if (timeid) timeid = clearTimeout(timeid);
                    if (settings.delay > 0 && timeout === undefined) return timeid = setTimeout(function() {
                        sendRequest(event, settings, cache, backSpace, TRUE);
                        timeid = clearTimeout(timeid);
                    },
                    settings.delay);
                    if (xhr) xhr.abort();
                    if (settings.useCache && cache.list[cache.val])
                    return loadResults(event, cache.list[cache.val], settings, cache, backSpace);
                    if (settings.dataSupply.length)
                    return userSuppliedData(event, settings, cache, backSpace);
                    if (settings.maxRequests && ++requests >= settings.maxRequests) {
                        $ul.html('').hide(event);
                        if (settings.spinner) settings.spinner.call(self, event, {
                            active: FALSE,
                            ul: $ul
                        });
                        return requests > settings.maxRequests ? FALSE: settings.onMaxRequest.apply(self, settings.backwardsCompatible ? [cache.val, $ul, event, inputval] : [event, {
                            search: cache.val,
                            val: inputval,
                            ul: $ul
                        }]);
                    }
                    settings.postData[settings.postVar] = cache.val
                    return xhr = $.ajax({
                        type: settings.requestType,
                        url: settings.ajax,
                        data: settings.postData,
                        dataType: 'json',
                        cache: settings.ajaxCache,
                        success: function(list) {
                            loadResults(event, list, settings, cache, backSpace);
                        },
                        error: function() {
                            $ul.html('').hide(event);
                            if (settings.spinner) settings.spinner.call(self, event, {
                                active: FALSE,
                                ul: $ul
                            });
                        },
                        xhr: function() {
                            if ($.browser.msie && $.browser.version.substr(0, 1) <= 7)
                            return new ActiveXObject("Microsoft.XMLHTTP");
                            else
                            return new XMLHttpRequest();
                        }
                    });
                }
                function userSuppliedData(event, settings, cache, backSpace) {
                    var list = [],
                    args = [],
                    fn = $.isFunction(settings.dataFn),
                    regex = fn ? undefined: new RegExp('^' + cache.val, 'i'),
                    k = 0,
                    entry,
                    i = 0,
                    l = settings.dataSupply.length;
                    for (; i < l; i++) {
                        entry = settings.dataSupply[i];
                        entry = typeof entry === 'object' && entry.value ? entry: {
                            value: entry
                        };
                        args = settings.backwardsCompatible ? [cache.val, entry.value, list, i, settings.dataSupply, $ul, event] : [event, {
                            val: cache.val,
                            entry: entry.value,
                            list: list,
                            i: i,
                            supply: settings.dataSupply,
                            ul: $ul
                        }];
                        if ((fn && settings.dataFn.apply(self, args)) || (!fn && entry.value.match(regex))) {
                            if (settings.maxItems > -1 && ++k > settings.maxItems)
                            break;
                            list.push(entry);
                        }
                    }
                    return loadResults(event, list, settings, cache, backSpace);
                }
                function select(event) {
                    if (ulOpen) {
                        if (settings.onSelect) settings.onSelect.apply(self, settings.backwardsCompatible ? [liData, $li, $ul, event] : [event, {
                            data: liData,
                            li: $li,
                            ul: $ul
                        }]);
                        autoFill(undefined);
                        inputval = $input.val();
                        if (LastEvent.type == 'keydown') LastEvent[$.expando + '_autoComplete_enter'] = TRUE;
                    }
                    $ul.hide(event);
                    return $li;
                }
                function up(event) {
                    if ($li) $li.removeClass(settings.rollover);
                    $ul.show(event);
                    $li = $elems.eq(liFocus).addClass(settings.rollover);
                    liData = $li.data(settings.dataName);
                    if (!$li.length || !liData) return FALSE;
                    if (settings.onRollover) settings.onRollover.apply(self, settings.backwardsCompatible ? [liData, $li, $ul, event] : [event, {
                        data: liData,
                        li: $li,
                        ul: $ul
                    }]);
                    var v = liFocus * liHeight;
                    if (v < view - ulHeight) {
                        view = v + ulHeight
                        $ul.scrollTop(v);
                    }
                    return $li;
                }
                function down(event) {
                    if ($li) $li.removeClass(settings.rollover);
                    $ul.show(event);
                    $li = $elems.eq(liFocus).addClass(settings.rollover);
                    liData = $li.data(settings.dataName);
                    if (!$li.length || !liData) return FALSE;
                    var v = (liFocus + 6) * liHeight;
                    if (v > view)
                    $ul.scrollTop((view = v) - ulHeight);
                    if (settings.onRollover) settings.onRollover.apply(self, settings.backwardsCompatible ? [liData, $li, $ul, event] : [event, {
                        data: liData,
                        li: $li,
                        ul: $ul
                    }]);
                    return $li;
                }
                function newUl() {
                    if (!$ul[$.expando + '_autoComplete']) {
                        var hide = $ul.hide,
                        show = $ul.show;
                        $ul.hide = function(event, speed, callback) {
                            if (settings.onHide && ulOpen) {
                                settings.onHide.call(self, event, {
                                    ul: $ul
                                });
                                LastEvent[$.expando + '_autoComplete_hide'] = TRUE;
                            }
                            ulOpen = FALSE;
                            return hide.call($ul, speed, callback);
                        };
                        $ul.show = function(event, speed, callback) {
                            if (settings.onShow && !ulOpen) settings.onShow.call(self, event, {
                                ul: $ul
                            });
                            ulOpen = TRUE;
                            return show.call($ul, speed, callback);
                        };
                        $ul[$.expando + '_autoComplete'] = TRUE;
                    }
                    var list = $ul.data('ac-inputs') || {};
                    list[inputIndex] = TRUE;
                    return $ul.data('ac-inputs', list);
                }
                function autoFill(val) {
                    if (val === undefined) {
                        var start,
                        end;
                        start = end = $input.val().length;
                    } else {
                        if (separator) val = inputval.substr(0, inputval.length - inputval.split(separator).pop().length) + val + separator;
                        var start = inputval.length,
                        end = val.length;
                        $input.val(val);
                    }
                    if (!settings.autoFill || start > end) {
                        return FALSE;
                    }
                    else if (self.createTextRange) {
                        var range = self.createTextRange();
                        if (val === undefined) {
                            range.move('character', start);
                            range.select();
                        } else {
                            range.collapse(TRUE);
                            range.moveStart("character", start);
                            range.moveEnd("character", end);
                            range.select();
                        }
                    }
                    else if (self.setSelectionRange) {
                        self.setSelectionRange(start, end);
                    }
                    else if (self.selectionStart) {
                        self.selectionStart = start;
                        self.selectionEnd = end;
                    }
                    return TRUE;
                }
                function loadResults(event, list, settings, cache, backSpace) {
                    if (settings.onLoad) list = settings.onLoad.call(self, event, {
                        list: list,
                        settings: settings,
                        cache: cache,
                        ul: $ul
                    });
                    if (settings.spinner) settings.spinner.call(self, event, {
                        active: FALSE,
                        ul: $ul
                    });
                    if (settings.useCache && cache.list[cache.val] === undefined) {
                        cache.length++;
                        cache.list[cache.val] = list;
                        if (cache.length > settings.cacheLimit) {
                            cache.list = {};
                            cache.length = 0;
                        }
                    }
                    if (!list || list.length < 1)
                    return $ul.html('').hide(event);
                    liFocus = -1;
                    var offset = $input.offset(),
                    container = [],
                    aci = 0,
                    k = 0,
                    i = 0,
                    even = FALSE,
                    length = list.length;
                    for (; i < length; i++) {
                        if (list[i].value || list[i].id) {
                            if (settings.maxItems > -1 && ++aci > settings.maxItems)
                            break;
                            container.push(settings.striped && even ? '<li class="' + settings.striped + '">': '<li>', list[i].display || list[i].value || list[i].name + ' - ' + list[i].id, '</li>');
                            even = !even;
                        }
                    }
                    $elems = $ul.html(container.join('')).children('li');
                    for (length = $elems.length; k < length; k++) {
                        $.data($elems[k], settings.dataName, list[k]);
                        $.data($elems[k], 'ac-index', k);
                    }
                    if (settings.autoFill && !backSpace) {
                        liFocus = 0;
                        liData = list[0];
                        autoFill(liData.value || '');
                        $li = $elems.eq(0).addClass(settings.rollover);
                    }
                    $ul.unbind('.autoComplete').data('ac-input-index', inputIndex).bind('mouseout.autoComplete',
                    function() {
                        $li.removeClass(settings.rollover);
                    }).bind('mouseover.autoComplete',
                    function(event) {
                        $li = $(event.target).closest('li');
                        if ($li.length < 1) return FALSE;
                        $elems.filter('.' + settings.rollover).removeClass(settings.rollover);
                        liFocus = $li.addClass(settings.rollover).data('ac-index');
                        liData = $li.data(settings.dataName);
                        if (settings.onRollover) settings.onRollover.apply(self, settings.backwardsCompatible ? [liData, $li, $ul, event] : [event, {
                            data: liData,
                            li: $li,
                            ul: $ul
                        }]);
                    }).bind('click.autoComplete',
                    function(event) {
                        $input.trigger('focus', [$.expando + '_autoComplete']);
                        liData = $li.data(settings.dataName);
                        $input.val(inputval = separator ? inputval.substr(0, inputval.length - inputval.split(separator).pop().length) + liData.value + separator: liData.value);
                        $ul.hide(event);
                        autoFill(undefined);
                        if (settings.onSelect) settings.onSelect.apply(self, settings.backwardsCompatible ? [liData, $li, $ul, event] : [event, {
                            data: liData,
                            li: $li,
                            ul: $ul
                        }]);
                    }).css({
                        top: offset.top + $input.outerHeight(),
                        left: offset.left + settings.leftAdjustment,
                        width: settings.width
                    }).scrollTop(0);
                    if (settings.maxHeight) $ul.css({
                        height: liHeight * $elems.length > settings.maxHeight ? settings.maxHeight: 'auto',
                        overflow: 'auto'
                    });
                    ulHeight = $ul.show(event).outerHeight();
                    view = ulHeight;
                    liHeight = $elems.eq(0).outerHeight();
                    liPerView = Math.floor(view / liHeight);
                    LastEvent.timeStamp = now();
                    return $ul;
                }
            });
        };
    })(jQuery);