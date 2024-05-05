/**
*  Ajax Autocomplete for jQuery, version %version%
*  (c) 2014 Tomas Kirda
*
*  Ajax Autocomplete for jQuery is freely distributable under the terms of an MIT-style license.
*  For details, see the web site: https://github.com/devbridge/jQuery-Autocomplete
*/

/*jslint  browser: true, white: true, plusplus: true, vars: true */
/*global define, window, document, jQuery, exports, require */

// Expose plugin as an AMD module if AMD loader is present:
(function (factory) {
    'use strict';
    if (typeof define === 'function' && define.amd) {
        // AMD. Register as an anonymous module.
        define(['jquery'], factory);
    } else if (typeof exports === 'object' && typeof require === 'function') {
        // Browserify
        factory(require('jquery'));
    } else {
        // Browser globals
        factory(jQuery);
    }
}(function ($) {
    'use strict';

    var keys = {
            ESC: 27,
            TAB: 9,
            RETURN: 13,
            LEFT: 37,
            UP: 38,
            RIGHT: 39,
            DOWN: 40
        };

    function Autocomplete(el, options) {
        var that = this,
            defaults = {
                ajaxSettings: {
                    url: null,
                    type: 'GET',
                    dataType: 'json'
                },
                appendTo: document.body,
                elementContainer: null,
                autoSelectFirst: false,
                className: 'suggestions',
                currentRequest: null,
                deferQueryBy: 0,
                delimiter: null,
                formatResult: function (suggestion, currentValue) {
                    var pattern = '(' + currentValue.replace(/[\-\[\]\/\{\}\(\)\*\+\?\.\\\^\$\|]/g, "\\$&") + ')';

                    return suggestion.value.replace(new RegExp(pattern, 'gi'), '<strong>$1<\/strong>');
                },
                forceFixPosition: true,
                lookup: null,
                lookupFilter: function (suggestion, originalQuery, queryLookupString) {
                    return this.options.lookupString.call(this, suggestion.value).indexOf(queryLookupString) !== -1;
                },
                lookupString: function (value) {
                    return (value || '').toLowerCase();
                },
                maxHeight: 300,
                minChars: 1,
                noCache: false,
                noSuggestionNotice: 'No results',
                onMove: $.noop,
                onSearchComplete: $.noop,
                onSearchError: $.noop,
                onSearchStart: $.noop,
                onSelect: null,
                orientation: 'bottom',
                params: {},
                paramName: 'q',
                preventBadQueries: true,
                searchOnFocus: false,
                selectDisabled: false,
                suggestionValue: function (suggestion) {
                    return suggestion.value;
                },
                showNoSuggestionNotice: false,
                tabDisabled: false,
                transformResult: function (response) {
                    return typeof response === 'string' ? $.parseJSON(response) : response;
                },
                triggerSelectOnValidInput: true,
                width: 'auto',
                zIndex: 9999
            };

        // Shared variables:
        that.input = el;
        that.$input = $(el);
        that.suggestions = [];
        that.badQueries = [];
        that.selectedIndex = -1;
        that.currentValue = that.$input.val();
        that.intervalId = 0;
        that.cachedResponse = {};
        that.onChangeInterval = null;
        that.onChange = null;
        that.isLocal = false;
        that.suggestionsContainer = null;
        that.noSuggestionsContainer = null;
        that.options = $.extend({}, defaults, options);
        that.hint = null;
        that.hintValue = '';
        that.selection = null;

        // Initialize and set options:
        that.initialize();
        that.setOptions(options);
    }

    $.Autocomplete = Autocomplete;

    Autocomplete.prototype = {

        killerFn: null,

        initialize: function () {
            var that = this,
                options = that.options,
                container;

            that.className = {
                wrapper: that.options.className,
                wrapperTop: that.options.className + '_top',
                header: that.options.className + '__header',
                body: that.options.className + '__body',
                footer: that.options.className + '__footer',
                empty: that.options.className + '__empty',
                group: that.options.className + '__group',
                item: that.options.className + '__item',
                itemSelected: that.options.className + '__item_selected',
            }

            // Remove autocomplete attribute to prevent native suggestions:
            that.input.setAttribute('autocomplete', 'off');

            that.killerFn = function (e) {
                if ($(e.target).closest('.' + that.className.wrapper).length === 0) {
                    that.killSuggestions();
                    that.disableKillerFn();
                }
            };

            // html() deals with many types: htmlString or Element or Array or jQuery
            that.noSuggestionsContainer = $('<div class="' + that.className.empty + '"></div>')
                                          .html(this.options.noSuggestionNotice).get(0);

            that.suggestionsContainer = $(
                '<div class="' + that.className.wrapper + '">' +
                    (this.options.header ?
                        '<div class="' + that.className.header + '">' + this.options.header + '</div>' :
                        '') +
                    '<div class="' + that.className.body + '"></div>' +
                    (this.options.footer ?
                        '<div class="' + that.className.footer + '">' + this.options.footer + '</div>' :
                        '') +
                '</div>'
            ).get(0);

            container = $(that.suggestionsContainer);

            container.appendTo(options.appendTo);

            // Only set width if it was provided:
            if (options.width !== 'auto') {
                container.width(options.width);
            }

            // Listen for mouse over event on suggestions list:
            container.on('mouseover.autocomplete', '.' + that.className.item, function () {
                that.activate($(this).data('index'));
            });

            // Deselect active element when mouse leaves suggestions container:
            container.on('mouseout.autocomplete', function () {
                that.selectedIndex = -1;
                container.children('.' + that.className.itemSelected).removeClass(that.className.itemSelected);
            });

            // Listen for click event on suggestions list:
            container.on('click.autocomplete', '.' + that.className.item, function () {
                that.select($(this).data('index'));
            });

            that.fixPositionCapture = function () {
                if (that.visible) {
                    that.fixPosition();
                }
            };

            $(window).on('resize.autocomplete', that.fixPositionCapture);

            if (options.elementContainer) {
                $(options.elementContainer).on('scroll.autocomplete', that.fixPositionCapture);
            }

            that.$input.on('keydown.autocomplete', function (e) { that.onKeyPress(e); });
            that.$input.on('keyup.autocomplete', function (e) { that.onKeyUp(e); });
            that.$input.on('blur.autocomplete', function () { that.onBlur(); });
            that.$input.on('focus.autocomplete', function () { that.onFocus(); });
            that.$input.on('change.autocomplete', function (e) { that.onKeyUp(e); });
            that.$input.on('input.autocomplete', function (e) { that.onKeyUp(e); });

            if ($.isFunction(this.options.onReady)){
                this.options.onReady.call(this, container);
            }
        },

        onFocus: function () {
            this.fixPosition();

            if ((this.options.minChars <= this.$input.val().length && this.options.lookup) ||
                (this.options.searchOnFocus && this.$input.val().length)) {
                this.onValueChange();
            }
        },

        onBlur: function () {
            this.enableKillerFn();
        },

        setOptions: function (suppliedOptions) {
            var that = this;

            $.extend(that.options, suppliedOptions);

            that.isLocal = $.isArray(that.options.lookup);
            that.options.orientation = that.validateOrientation(that.options.orientation, 'bottom');

            // Adjust height, width and z-index:
            $(that.suggestionsContainer)
                .css({
                    'z-index': that.options.zIndex,
                    'width': that.options.width
                })
                .find('.' + that.className.body)
                .css({
                    'max-height': that.options.maxHeight
                });
        },


        clearCache: function () {
            this.cachedResponse = {};
            this.badQueries = [];
        },

        clear: function () {
            this.clearCache();
            this.currentValue = '';
            this.suggestions = [];
        },

        disable: function () {
            var that = this;
            that.disabled = true;
            clearInterval(that.onChangeInterval);
            if (that.currentRequest) {
                that.currentRequest.abort();
            }
        },

        enable: function () {
            this.disabled = false;
        },

        fixPosition: function () {
            // Use only when container has already its content

            var that = this,
                $container = $(that.suggestionsContainer),
                containerParent = $container.parent().get(0);

            // Fix position automatically when appended to body.
            // In other cases force parameter must be given.
            if (containerParent !== document.body && !that.options.forceFixPosition) {
                return;
            }

            // Choose orientation
            var orientation = that.options.orientation,
                containerHeight = $container.outerHeight(),
                height = that.$input.outerHeight(),
                offset = that.$input.offset(),
                styles = { 'top': offset.top, 'left': offset.left + 2 };

            if (orientation === 'auto') {
                var viewPortHeight = $(window).height(),
                    scrollTop = $(window).scrollTop(),
                    topOverflow = -scrollTop + offset.top - containerHeight,
                    bottomOverflow = scrollTop + viewPortHeight - (offset.top + height + containerHeight);

                orientation = (Math.max(topOverflow, bottomOverflow) === topOverflow) ? 'top' : 'bottom';
            }

            if (orientation === 'top') {
                styles.top += -containerHeight;

                $container.addClass(that.className.wrapperTop);
            } else {
                styles.top += height;

                $container.removeClass(that.className.wrapperTop);
            }

            // If container is not positioned to body,
            // correct its position using offset parent offset
            if (containerParent !== document.body) {
                var opacity = $container.css('opacity'),
                    parentOffsetDiff;

                if (!that.visible){
                    $container.css('opacity', 0).show();
                }

                parentOffsetDiff = $container.offsetParent().offset();

                styles.top -= parentOffsetDiff.top;
                styles.top += $(containerParent).scrollTop();
                styles.left -= parentOffsetDiff.left;

                if (!that.visible){
                    $container.css('opacity', opacity).hide();
                }
            }

            if (that.options.width === 'auto') {
                styles.width = (that.$input.outerWidth() - 4) + 'px';
            }

            $container.css(styles);
        },

        enableKillerFn: function () {
            var that = this;
            $(document).on('click.autocomplete', that.killerFn);
        },

        disableKillerFn: function () {
            var that = this;
            $(document).off('click.autocomplete', that.killerFn);
        },

        killSuggestions: function () {
            var that = this;
            that.stopKillSuggestions();
            that.intervalId = window.setInterval(function () {
                that.hide();
                that.stopKillSuggestions();
            }, 50);
        },

        stopKillSuggestions: function () {
            window.clearInterval(this.intervalId);
        },

        isCursorAtEnd: function () {
            var that = this,
                valLength = that.$input.val().length,
                selectionStart = that.input.selectionStart,
                range;

            if (typeof selectionStart === 'number') {
                return selectionStart === valLength;
            }
            if (document.selection) {
                range = document.selection.createRange();
                range.moveStart('character', -valLength);
                return valLength === range.text.length;
            }
            return true;
        },

        onKeyPress: function (e) {
            var that = this;

            // If suggestions are hidden and user presses arrow down, display suggestions:
            if (!that.disabled && !that.visible && e.which === keys.DOWN && that.currentValue) {
                that.suggest();
                return;
            }

            if (that.disabled || !that.visible) {
                return;
            }

            switch (e.which) {
                case keys.ESC:
                    that.$input.val(that.currentValue);
                    that.hide();
                    break;
                case keys.RIGHT:
                    if (that.hint && that.options.onHint && that.isCursorAtEnd()) {
                        that.selectHint();
                        break;
                    }
                    return;
                case keys.TAB:
                    if (that.hint && that.options.onHint) {
                        that.selectHint();
                        return;
                    }
                    if (that.selectedIndex === -1) {
                        that.hide();
                        return;
                    }
                    that.select(that.selectedIndex);
                    if (that.options.tabDisabled === false) {
                        return;
                    }
                    break;
                case keys.RETURN:
                    if (that.selectedIndex === -1) {
                        that.hide();
                        return;
                    }
                    that.select(that.selectedIndex);
                    break;
                case keys.UP:
                    if (!that.options.selectDisabled) {
                        that.moveUp();
                    }
                    break;
                case keys.DOWN:
                    if (!that.options.selectDisabled) {
                        that.moveDown();
                    }
                    break;
                default:
                    return;
            }

            // Cancel event if function did not return:
            e.stopImmediatePropagation();
            e.preventDefault();
        },

        onKeyUp: function (e) {
            var that = this;

            if (that.disabled) {
                return;
            }

            switch (e.which) {
                case keys.UP:
                case keys.DOWN:
                    return;
            }

            clearInterval(that.onChangeInterval);

            if (that.currentValue !== that.$input.val()) {
                that.findBestHint();
                if (that.options.deferQueryBy > 0) {
                    // Defer lookup in case when value changes very quickly:
                    that.onChangeInterval = setInterval(function () {
                        that.onValueChange();
                    }, that.options.deferQueryBy);
                } else {
                    that.onValueChange();
                }
            }
        },

        onValueChange: function () {
            var that = this,
                options = that.options,
                value = that.$input.val(),
                query = that.getQuery(value),
                index;

            if (that.selection && that.currentValue !== query) {
                that.selection = null;
                (options.onInvalidateSelection || $.noop).call(that.input);
            }

            clearInterval(that.onChangeInterval);
            that.currentValue = value;
            that.selectedIndex = -1;

            // Check existing suggestion for the match before proceeding:
            if (options.triggerSelectOnValidInput) {
                index = that.findSuggestionIndex(query);
                if (index !== -1) {
                    that.onSelect(index, true);

                    if (!that.suggestions.length) {
                        that.hide();

                        return;
                    }
                }
            }

            if (query.length < options.minChars) {
                that.hide();
            } else {
                that.getSuggestions(query);
            }
        },

        findSuggestionIndex: function (query) {
            var that        = this,
                index       = -1,
                queryString = that.options.lookupString.call(that, query.toLowerCase());

            $.each(that.suggestions, function (i, suggestion) {
                var suggestionString = that.options.lookupString.call(that, suggestion.value);

                if (suggestionString === queryString) {
                    index = i;
                    return false;
                }
            });

            return index;
        },

        getQuery: function (value) {
            var delimiter = this.options.delimiter,
                parts;

            if (!delimiter) {
                return value;
            }
            parts = value.split(delimiter);
            return $.trim(parts[parts.length - 1]);
        },

        getSuggestionsLocal: function (query) {
            var that = this,
                queryLookupString = that.options.lookupString.call(that, query),
                limit = parseInt(that.options.lookupLimit, 10);

            var data = {
                suggestions: $.grep(that.options.lookup, function (suggestion) {
                    return that.options.lookupFilter.call(that, suggestion, query, queryLookupString);
                })
            };

            if (limit && data.suggestions.length > limit) {
                data.suggestions = data.suggestions.slice(0, limit);
            }

            return data;
        },

        getSuggestions: function (q) {
            var response, params, cacheKey,
                that = this;

            if (typeof(that.options.params) === 'function') {
                params = that.options.params(q);
            } else {
                params = that.options.params;
                params[that.options.paramName] = q;
            }

            if (that.options.onSearchStart.call(that.input, params) === false) {
                return;
            }

            params = that.options.ignoreParams ? null : params;

            if ($.isFunction(that.options.lookup)){
                that.options.lookup(q, function (data) {
                    that.suggestions = data.suggestions;
                    that.suggest();
                    that.options.onSearchComplete.call(that.input, q, data.suggestions);
                });
                return;
            }

            if (that.isLocal) {
                response = that.getSuggestionsLocal(q);
            } else {
                cacheKey = that.options.ajaxSettings.url + '?' + $.param(params || {});
                response = that.cachedResponse[cacheKey];
            }

            if (response && $.isArray(response.suggestions)) {
                that.suggestions = response.suggestions;
                that.suggest();
                that.options.onSearchComplete.call(that.input, q, response.suggestions);
            } else if (!that.isBadQuery(q)) {
                if (that.currentRequest) {
                    that.currentRequest.abort();
                }

                that.currentRequest = $.ajax($.extend({}, { data: params }, that.options.ajaxSettings)).done(function (data) {
                    var result;
                    that.currentRequest = null;
                    result = that.options.transformResult(data);
                    that.processResponse(result, q, cacheKey);
                    that.options.onSearchComplete.call(that.input, q, result.suggestions);
                }).fail(function (jqXHR, textStatus, errorThrown) {
                    that.options.onSearchError.call(that.input, q, jqXHR, textStatus, errorThrown);
                });
            } else {
                that.options.onSearchComplete.call(that.input, q, []);
            }
        },

        isBadQuery: function (q) {
            if (!this.options.preventBadQueries){
                return false;
            }

            var badQueries = this.badQueries,
                i = badQueries.length;

            while (i--) {
                if (q.indexOf(badQueries[i]) === 0) {
                    return true;
                }
            }

            return false;
        },

        hide: function () {
            var that = this;
            that.visible = false;
            that.selectedIndex = -1;
            clearInterval(that.onChangeInterval);
            $(that.suggestionsContainer).hide();
            that.signalHint(null);
        },

        suggest: function () {
            if (this.suggestions.length === 0) {
                if (this.options.showNoSuggestionNotice) {
                    this.noSuggestions();
                } else {
                    this.hide();
                }
                return;
            }

            var that = this,
                options = that.options,
                groupBy = options.groupBy,
                formatResult = options.formatResult,
                value = that.getQuery(that.currentValue),
                container = $(that.suggestionsContainer).find('.' + that.className.body),
                noSuggestionsContainer = $(that.noSuggestionsContainer),
                beforeRender = options.beforeRender,
                html = '',
                category,
                formatGroup = function (suggestion, index) {
                        var currentCategory = suggestion.data[groupBy];

                        if (category === currentCategory){
                            return '';
                        }

                        category = currentCategory;

                        return '<div class="' + that.className.group + '">' + category + '</div>';
                    },
                index;

            if (options.triggerSelectOnValidInput) {
                index = that.findSuggestionIndex(value);
                if (index !== -1) {
                    that.onSelect(index, true);

                    if (!that.suggestions.length) {
                        that.hide();

                        return;
                    }
                }
            }

            // Build suggestions inner HTML:
            $.each(that.suggestions, function (i, suggestion) {
                if (groupBy){
                    html += formatGroup(suggestion, value, i);
                }

                html += '<div class="' + that.className.item + '" data-index="' + i + '">' + formatResult.call(that, suggestion, value) + '</div>';
            });

            this.adjustContainerWidth();

            noSuggestionsContainer.detach();
            container.html(html);

            if ($.isFunction(beforeRender)) {
                beforeRender.call(that.input, container, that.suggestions);
            }

            that.fixPosition();
            $(that.suggestionsContainer).show();

            // Select first value by default:
            if (options.autoSelectFirst) {
                that.selectedIndex = 0;
                container.scrollTop(0);
                container.children().first().addClass(that.className.itemSelected);
            }

            that.visible = true;
            that.findBestHint();
        },

        noSuggestions: function() {
             var that = this,
                 container = $('.' + that.className.body, that.suggestionsContainer),
                 noSuggestionsContainer = $(that.noSuggestionsContainer);

            this.adjustContainerWidth();

            // Some explicit steps. Be careful here as it easy to get
            // noSuggestionsContainer removed from DOM if not detached properly.
            noSuggestionsContainer.detach();
            container.empty(); // clean suggestions if any
            container.append(noSuggestionsContainer);

            that.fixPosition();

            $(that.suggestionsContainer).show();
            that.visible = true;
        },

        adjustContainerWidth: function() {
            var that = this,
                options = that.options,
                width,
                container = $(that.suggestionsContainer);

            // If width is auto, adjust width before displaying suggestions,
            // because if instance was created before input had width, it will be zero.
            // Also it adjusts if input width has changed.
            // -2px to account for suggestions border.
            if (options.width === 'auto') {
                width = that.$input.outerWidth() - 2;
                container.width(width > 0 ? width : 300);
            }
        },

        findBestHint: function () {
            var that = this,
                value = that.options.lookupString.call(that, that.$input.val()),
                bestMatch = null;

            if (!value) {
                return;
            }

            $.each(that.suggestions, function (i, suggestion) {
                var foundMatch = that.options.lookupString.call(that, suggestion.value).indexOf(value) === 0;
                if (foundMatch) {
                    bestMatch = suggestion;
                }
                return !foundMatch;
            });

            that.signalHint(bestMatch);
        },

        signalHint: function (suggestion) {
            var hintValue = '',
                that = this;
            if (suggestion) {
                hintValue = that.currentValue + suggestion.value.substr(that.currentValue.length);
            }
            if (that.hintValue !== hintValue) {
                that.hintValue = hintValue;
                that.hint = suggestion;
                (this.options.onHint || $.noop)(hintValue);
            }
        },

        validateOrientation: function(orientation, fallback) {
            orientation = (orientation || '').toLowerCase();

            if (['auto', 'bottom', 'top'].indexOf(orientation) === -1)
                return fallback;

            return orientation;
        },

        processResponse: function (result, originalQuery, cacheKey) {
            var that = this,
                options = that.options;

            // Cache results if cache is not disabled:
            if (!options.noCache) {
                that.cachedResponse[cacheKey] = result;
                if (options.preventBadQueries && result.suggestions.length === 0) {
                    that.badQueries.push(originalQuery);
                }
            }

            // Return if originalQuery is not matching current query:
            if (originalQuery !== that.getQuery(that.currentValue)) {
                return;
            }

            that.suggestions = result.suggestions;
            that.suggest();
        },

        activate: function (index) {
            var that = this,
                activeItem,
                container = $(that.suggestionsContainer),
                children = container.find('.' + that.className.item);

            container.find('.' + that.className.itemSelected).removeClass(that.className.itemSelected);

            that.selectedIndex = index;

            if (that.selectedIndex !== -1 && children.length > that.selectedIndex) {
                activeItem = children.get(that.selectedIndex);
                $(activeItem).addClass(that.className.itemSelected);
                return activeItem;
            }

            return null;
        },

        selectHint: function () {
            var that = this,
                i = $.inArray(that.hint, that.suggestions);

            that.select(i);
        },

        select: function (i) {
            var that = this,
                suggestion = that.suggestions[i];

            if (!suggestion.disabled) {
                that.hide();
                that.onSelect(i);
            } else {
                that.$input.val(that.currentValue).trigger('pick');
            }
        },

        moveUp: function () {
            var that = this;

            if (that.selectedIndex === -1) {
                return;
            }

            if (that.selectedIndex === 0) {
                $(that.suggestionsContainer).find('.' + that.className.body).children().first().removeClass(that.className.itemSelected);
                that.selectedIndex = -1;
                that.$input.val(that.currentValue);
                that.findBestHint();
                return;
            }

            that.adjustScroll(that.selectedIndex - 1);
        },

        moveDown: function () {
            var that = this;

            if (that.selectedIndex === (that.suggestions.length - 1)) {
                return;
            }

            that.adjustScroll(that.selectedIndex + 1);
        },

        adjustScroll: function (index) {
            var that = this,
                activeItem = that.activate(index);

            if (!activeItem) {
                return;
            }

            var offsetTop,
                upperBound,
                lowerBound,
                heightDelta = $(activeItem).outerHeight(),
                $container  = $(that.suggestionsContainer).find('.' + that.className.body),
                suggestion = that.suggestions[index];

            offsetTop = activeItem.offsetTop;
            upperBound = $container.scrollTop();
            lowerBound = upperBound + that.options.maxHeight - heightDelta;

            if (offsetTop < upperBound) {
                $container.scrollTop(offsetTop);
            } else if (offsetTop > lowerBound) {
                $container.scrollTop(offsetTop - that.options.maxHeight + heightDelta);
            }

            that.options.onMove(suggestion, that.getValue(that.getSuggestionValue(suggestion)));

            that.$input.val(that.getValue(that.getSuggestionValue(suggestion)));
            that.signalHint(null);
        },

        getSuggestionValue: function(suggestion) {
            return this.options.suggestionValue.call(this, suggestion);
        },

        onSelect: function (index, keepSuggestions) {
            var that = this,
                onSelectCallback = that.options.onSelect,
                suggestion = that.suggestions[index];

            that.currentValue = that.getValue(that.getSuggestionValue(suggestion));

            if (!that.options.selectDisabled) {
                if (that.currentValue !== that.$input.val()) {
                    that.$input.val(that.currentValue);
                }

                if (that.suggestions.length === 1) {
                    that.suggestions.shift(index);
                }

                that.signalHint(null);
                that.selection = suggestion;

                if (!keepSuggestions) {
                    that.suggestions = [];
                }
            }

            if ($.isFunction(onSelectCallback)) {
                onSelectCallback.call(that.input, suggestion);
            }
        },

        getValue: function (value) {
            var that = this,
                delimiter = that.options.delimiter,
                currentValue,
                parts;

            if (!delimiter) {
                return value;
            }

            currentValue = that.currentValue;
            parts = currentValue.split(delimiter);

            if (parts.length === 1) {
                return value;
            }

            return currentValue.substr(0, currentValue.length - parts[parts.length - 1].length) + value;
        },

        dispose: function () {
            var that = this;
            that.$input.off('.autocomplete').removeData('autocomplete');
            that.disableKillerFn();
            $(window).off('resize.autocomplete', that.fixPositionCapture);
            $(that.suggestionsContainer).remove();
        }
    };

    // Create chainable jQuery plugin:
    $.fn.autocomplete = function (options, args) {
        var dataKey = 'autocomplete';
        // If function invoked without argument return
        // instance of the first matched element:
        if (arguments.length === 0) {
            return this.first().data(dataKey);
        }

        return this.each(function () {
            var inputElement = $(this),
                instance = inputElement.data(dataKey);

            if (typeof options === 'string') {
                if (instance && typeof instance[options] === 'function') {
                    instance[options](args);
                }
            } else {
                // If instance already exists, destroy it:
                if (instance && instance.dispose) {
                    instance.dispose();
                }
                instance = new Autocomplete(this, options);
                inputElement.data(dataKey, instance);
            }
        });
    };
}));
