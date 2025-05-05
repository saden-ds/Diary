jQuery(function($){
  $.datepicker.regional['lv'] = {
    closeText: 'Aizvērt',
    prevText: 'Iepr.',
    nextText: 'Nāk.',
    currentText: 'Šodien',
    monthNames: ['Janvāris', 'Februāris', 'Marts', 'Aprīlis', 'Maijs', 'Jūnijs',
    'Jūlijs', 'Augusts', 'Septembris', 'Oktobris', 'Novembris', 'Decembris'],
    monthNamesShort: ['Jan', 'Feb', 'Mar', 'Apr', 'Mai', 'Jūn',
    'Jūl', 'Aug', 'Sep', 'Okt', 'Nov', 'Dec'],
    dayNames: [
      'svētdiena',
      'pirmdiena',
      'otrdiena',
      'trešdiena',
      'ceturtdiena',
      'piektdiena',
      'sestdiena'
    ],
    dayNamesShort: ['svt', 'prm', 'otr', 'tre', 'ctr', 'pkt', 'sst'],
    dayNamesMin: ['Sv', 'Pr', 'Ot', 'Tr', 'Ct', 'Pk', 'Ss'],
    weekHeader: 'Ned.',
    dateFormat: 'dd.mm.yy.',
    firstDay: 1,
    isRTL: false,
    showMonthAfterYear: false,
    yearSuffix: ''
  };
});

$.ajaxPrefilter(function(options, originalOptions, xhr){
  var token = $('meta[name="csrf"]').attr('content');
  if (token) {
    xhr.setRequestHeader('X-CSRF-Token', token);
  }
});

function namespace(namespaceString) {
    var parts = namespaceString.split('.'),
        parent = window,
        currentPart = '';

    for(var i = 0, length = parts.length; i < length; i++) {
        currentPart = parts[i];
        parent[currentPart] = parent[currentPart] || {};
        parent = parent[currentPart];
    }

    return parent;
}

namespace('app');


(function($) {
  $.fn.overlay = function(options) {
    if (options === 'remove') {
      return this.each(function(){
        var $container = $(this),
            $overlay   = $container.find('.overlay');

        $container.removeClass('relative');
        $overlay.remove();
      });
    }

    return this.each(function(){
      var $overlay, $content,
          $container = $(this);

      $overlay = $(
        '<div class="overlay">' +
          '<div class="overlay__loader">' +
            '<div class="loader-ticker overlay__ticker">' +
              '<div class="loader-ticker__bar"></div>' +
            '</div>' +
          '</div>' +
          '<div class="overlay__bg"></div>' +
        '</div>'
      ).prependTo(this);
      $content = $overlay.find('.overlay__loader');

      function offset() {
        var h  = $container.prop('clientHeight'),
            w  = $container.prop('clientWidth'),
            sh = $container.prop('scrollHeight');

        $content.css({ height: h });
        $overlay.css({ width: w, height: sh });
      }

      if ($container.css('position') === 'static') {
        $container.addClass('relative');
      }

      offset();

      $(window).resize(function() {
        offset();
      });
    });
  };
})(jQuery);


/**
 * Textareas auto growing
 */
(function($){
  $.fn.autogrow = function(options) {
    if (options === 'reset') {
      return this.filter('textarea').height('auto');
    } else {
      return this.filter('textarea').each(function(){
        var $shadow,
            self    = this,
            $self   = $(this),
            height  = $self.outerHeight();

        $shadow = $('<div></div>').css({
          position:   'absolute',
          top:        -10000,
          left:       -10000,
          width:      $self.width(),
          fontSize:   $self.css('fontSize'),
          fontFamily: $self.css('fontFamily'),
          fontWeight: $self.css('fontWeight'),
          lineHeight: $self.css('lineHeight'),
          resize:     'none'
        }).appendTo(document.body);

        var times = function(string, number) {
          for (var i = 0, r = ''; i < number; i++) r += string;
          return r;
        };
        var update = function() {
          var value = '&nbsp;';

          if (self.value !== '')
            value = self.value
              .replace(/&/g, '&amp;')
              .replace(/</g, '&lt;')
              .replace(/>/g, '&gt;')
              .replace(/\n$/, '<br>&nbsp;')
              .replace(/\n/g, '<br>')
              .replace(/ {2,}/g, function(space){
                return times('&nbsp;', space.length - 1) + ' '
              });

            $shadow.css('width', $self.width());
            $shadow.html(value);
            $self.outerHeight($shadow.height());
            $self.outerHeight(self.scrollHeight);
          }

          $self.addClass('grown');
          $self.on('input', update);
          $self.on('keyup', update);
          $self.on('keydown', update);
          $(window).resize(update);

          update();
      });
    }
  };
})(jQuery);


(function($) {
  $.fn.ticker = function(options) {
    if (options === 'remove') {
      return this.each(function(){
        var $container = $(this),
            $ticker    = $container.find('.loader-replacement, .loader-ticker');

        $ticker.remove();
      });
    }
    if (options === 'replace') {
      return this.prepend(
        '<div class="loader-replacement">' +
          '<div class="loader-ticker">' +
            '<div class="loader-ticker__bar"></div>' +
          '</div>' +
        '</div>'
      );
    }

    return this.prepend(
      '<div class="loader-ticker">' +
        '<div class="loader-ticker__bar"></div>' +
      '</div>'
    );
  };
})(jQuery);

(function($){
  function scorePassword(pass) {
    var score          = 0,
        letters        = new Object(),
        variationCount = 0,
        variations     = {
          digits: /\d/.test(pass),
          lower: /[a-z]/.test(pass),
          upper: /[A-Z]/.test(pass),
          nonWords: /\W/.test(pass)
        };

    if (!pass) {
      return score;
    }

    // award every unique letter until 5 repetitions
    for (var i = 0; i < pass.length; i++) {
      letters[pass[i]] = (letters[pass[i]] || 0) + 1;
      score += 5.0 / letters[pass[i]];
    }
    for (var check in variations) {
      variationCount += (variations[check] == true) ? 1 : 0;
    }
    score += (variationCount - 1) * 10;

    return score;
  }

  $.fn.passwordMeter = function() {
    return this.filter('input[type=password]').each(function(){
      var $this  = $(this),
          $meter = $('<span class="passmeter"><span class="passmeter__bar"></span></span>'),
          $bar   = $meter.find('.passmeter__bar');

      $this.before($meter);

      $this.on('keyup change pick', function(){
        var score = scorePassword(this.value);

        if (this.value.length < 12) {
          score = Math.min(50, score);
        }

        if (!score) {
          $meter.prop('class', 'passmeter');
        } else if (score < 30) {
          $meter.prop('class', 'passmeter passmeter_active passmeter_bad');
        } else if (score < 60) {
          $meter.prop('class', 'passmeter passmeter_active passmeter_weak');
        } else if (score < 80) {
          $meter.prop('class', 'passmeter passmeter_active passmeter_good');
        } else {
          $meter.prop('class', 'passmeter passmeter_active passmeter_strong');
        }

        $bar.width(Math.min(100, Math.max(10, score)) + '%');
      });
    });
  };
})(jQuery);

(function(app){
  var transitionend = 'transitionend webkitTransitionEnd oTransitionEnd MSTransitionEnd';

  function Dialog(callback, options) {
    this.options = $.extend({
      message: 'Are you sure?',
      yes: 'Cancel',
      no: 'Ok',
      type: null
    }, options);
    this.callback = callback;
    this.show();
  }

  Dialog.prototype.show = function() {
    var self   = this,
        cancel = '';

    if (self.options.no) {
      cancel = (
        '<button class="button button_small button_simple dialog__cancel">' +
          self.options.no +
        '</button> '
      );
    }

    self.$dialog = $(
      '<div class="dialog fade"> ' +
        '<div class="dialog__box"> ' +
          '<div class="dialog__body">' +
            self.options.message +
          '</div> ' +
          '<div class="dialog__footer">' +
            cancel +
            '<button class="button button_small button_submit dialog__commit' +
              (self.options.type ? ' button_' + self.options.type : '') + '">' +
              self.options.yes +
            '</button> ' +
          '</div> ' +
        '</div> ' +
      '</div>'
    );

    self.$dialog.appendTo('body');
    self.$dialog.find('.dialog__commit').click(function(){
      self.remove();

      if (typeof(self.callback) === 'function') {
        self.callback();
      }
    });
    self.$dialog.find('.dialog__cancel').click(function(){
      self.remove();
    });

    setTimeout(function(){
      self.$dialog.addClass('show');
      self.$dialog.one(transitionend, function(){
        self.$dialog.find('.dialog__commit').focus();
      });
    }, 0);
  };

  Dialog.prototype.remove = function() {
    var self = this;

    self.$dialog.removeClass('show');
    self.$dialog.one(transitionend, function(){
      self.$dialog.remove();
    });
  };

  app.Dialog = Dialog;
})(app);

(function(app){
  function Lookup(selector, options) {
    var self = this;

    self.options = $.extend({
      scrollableSelector: '#aside',
      containerSelector: '#aside_nav',
      anchorSelector: '.js_aside_nav_item',
      emptySelector: null,
      onSelect: function ($selected, selected) {
        navTo(selected);
      }
    }, options);

    self.$input      = $(selector),
    self.$container  = $(self.options.containerSelector),
    self.$anchors    = self.$container.find(self.options.anchorSelector),
    self.selected    = -1,
    self.valueWas    = self.$input.val(),
    self.defaultHtml = self.options.emptySelector ? '' : self.$container.html();

    if (!self.options.paramName) {
      self.options.paramName = self.$input.attr('name');
    }

    self.$input.on('focus pick', function(event){
      $anchors = self.$container.find(self.options.anchorSelector);
    });

    self.$input.on('paste', function(event){
      self.onChangeInterval = setTimeout(function(){
        self.search(self.$input.val());
      }, 100);
    });

    self.$input.keydown(function(event){
      var $selected,
          keyCode = event.keyCode;

      if (keyCode === 13) {
        event.preventDefault();
      }

      if (keyCode === 38) {
        self.selected = Math.max(-1, self.selected - 1);
        self.$anchors.removeClass('focus');

        if (self.selected === -1) return;

        $selected = self.$anchors.eq(self.selected);
        $selected.addClass('focus');
        self.adjustScroll($selected);
      }

      if (keyCode === 40) {
        self.selected = Math.min($anchors.length - 1, self.selected + 1);
        $selected = self.$anchors.eq(self.selected);
        self.$anchors.removeClass('focus');
        $selected.addClass('focus');
        self.adjustScroll($selected);
      }
    });

    self.$input.keyup(function(event){
      var value   = this.value,
          keyCode = event.keyCode;

      if (value === self.valueWas) {
        if (keyCode === 13) {
          self.$anchors.removeClass('focus');
          self.selected = self.selected === -1 ? 0 : self.selected;
          $selected = self.$anchors.eq(self.selected);

          if (typeof(self.options.onSelect) === 'function') {
            self.options.onSelect($selected, self.selected);
          }
        }

        return;
      }

      self.search(value);
    });
  }

  Lookup.prototype.search = function(value) {
    var self = this,
        data = {};

    data[self.options.paramName] = value;
    self.valueWas = value;

    clearTimeout(self.onChangeInterval);

    if (value.length > 1) {
      self.$container.unhighlight();
      self.$container.highlight(value.split(' '));

      self.onChangeInterval = setTimeout(function(){
        if (self.request) {
          self.request.abort();
        }

        if (typeof(self.options.setDataBeforeSend) === 'function') {
          data = self.options.setDataBeforeSend(data);
        }

        self.request = $.ajax({ url: self.options.url, data: data, dataType: 'html' }).done(function (html) {
          self.request = null;

          self.$container.html(html).highlight(value.split(' '));
          self.$anchors = self.$container.find(self.options.anchorSelector);
          self.selected = -1;

          $(self.options.scrollableSelector).scrollTop(0);
          $(self.options.emptySelector).addClass('hidden');

          if (typeof(self.options.onComplete) === 'function') {
            self.options.onComplete();
          }
        }).fail(function(jqXHR, textStatus, errorThrown) {
        });
      }, 100);
    } else if (self.options.emptySelector) {
      self.$container.empty();
      $(self.options.emptySelector).removeClass('hidden');
    } else {
      self.$container.html(self.defaultHtml);
      self.$anchors = self.$container.find(self.options.anchorSelector);

      if (typeof(self.options.onComplete) === 'function') {
        self.options.onComplete();
      }
    }
  };

  Lookup.prototype.adjustScroll = function($selected) {
    if (!$selected.length) return;

    var $scrollable         = $(this.options.scrollableSelector),
        scrollableScrollTop = $scrollable.scrollTop(),
        scrollableTop       = $scrollable.offset().top,
        scrollableHeight    = $scrollable.height(),
        containerTop        = $(this.options.containerSelector).offset().top - scrollableTop + scrollableScrollTop,
        selectedTop         = $selected.offset().top - scrollableTop,
        selectedHeight      = $selected.outerHeight();

    if (selectedTop < containerTop) {
      $scrollable.scrollTop(scrollableScrollTop + selectedTop - containerTop);
    } else if (selectedTop + selectedHeight > scrollableHeight) {
      $scrollable.scrollTop(scrollableScrollTop + selectedTop - scrollableHeight + selectedHeight);
    }
  };

  app.Lookup = Lookup;
})(app);

(function(app){
  function CookieConfirm(container) {
    var self = this;

    $(container + ' .js_action').click(function(event){
      if (this.hash === '#remove') {
        event.preventDefault();
      }

      self.set('life_cookie_confirm', + new Date(), 365);

      $(container).fadeOut(function(){
        $(this).remove();
      });
    });
  }

  CookieConfirm.prototype.set = function(name, value, days) {
    var date = new Date();

    date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));

    document.cookie = name + '=' + value + ';expires=' + date.toUTCString() + ';path=/';
  }

  CookieConfirm.prototype.get = function(name) {
    var ca = document.cookie.split(';');
    var name = name + '=';

    for(var i = 0; i < ca.length; i++) {
        var c = ca[i];

        while (c.charAt(0) == ' ') {
            c = c.substring(1);
        }

        if (c.indexOf(name) == 0) {
            return c.substring(name.length, c.length);
        }
    }

    return '';
  }

  app.CookieConfirm = CookieConfirm;
})(app);

(function(app){
  function Autocomplete(selector, options) {
    var self = this;

    self.options = $.extend({
      url: '/api',
      valueName: 'name',
      idName: 'id',
      minChars: 2,
      noSuggestionNotice: null,
      formatResult: function (suggestion, currentValue) {
        return self.highlight(this, suggestion.value, currentValue);
      }
    }, options);
    self.$input = $(selector);

    if (self.options.selector) {
      self.hidden();
    }

    self.autocomplete();
  }

  Autocomplete.prototype.hidden = function() {
    var self = this;

    self.$hidden = $(self.options.selector);
    self.query = $.trim(self.$input.val());

    self.$input.on('keyup blur', function(event){
      if ($.trim(self.$input.val()) !== self.query) {
        self.$hidden.val('');
        self.query = '';

        if (event.type === 'blur') {
          self.$input.val('');
        }
      }

      if (typeof(self.options.onChange) === 'function') {
        self.options.onChange(!self.query && !self.$hidden.val());
      }
    });
  };

  Autocomplete.prototype.autocomplete = function() {
    var self = this;

    this.$input.autocomplete({
      appendTo: self.options.appendTo,
      paramName: 'q',
      tabDisabled: true,
      triggerSelectOnValidInput: false,
      maxHeight: 200,
      preventBadQueries: false,
      minChars: self.options.minChars,
      deferRequestBy: 30,
      ajaxSettings: {
        url: self.options.url,
        type: 'GET',
        dataType: 'json'
      },
      showNoSuggestionNotice: !!self.options.noSuggestionNotice,
      noSuggestionNotice: self.options.noSuggestionNotice,
      params: self.options.params,
      transformResult: function (response) {
        var result = { suggestions: [] };

        if (!response) {
          return result;
        }
        if (self.options.resource) {
          response = response[self.options.resource];
        }
        if (response) {
          $.each(response, function(i, data){
            result.suggestions.push({
              value: data[self.options.valueName],
              data: data
            });
          });
        }

        return result;
      },
      lookupString: function(value) {
        return self.replace((value || '').toLowerCase());
      },
      formatResult: self.options.formatResult,
      onSelect: function (suggestion) {
        if (self.$hidden) {
          self.query = $.trim(suggestion.value);
          self.$hidden.val(suggestion.data[self.options.idName]);
        }

        self.$input.trigger('pick');

        if (suggestion.data) {
          if (typeof(self.options.onSelect) === 'function') {
            self.options.onSelect(suggestion);
          }
        }
      }
    });
  };

  Autocomplete.prototype.replace = function(string) {
    if (!string) return string;

    string = string.replace(/[āčēģīķļņšūž]/gi, function (m) {
      return {
        'ā': 'a',
        'č': 'c',
        'ē': 'e',
        'ģ': 'g',
        'ī': 'i',
        'ķ': 'k',
        'ļ': 'l',
        'ņ': 'n',
        'š': 's',
        'ū': 'u',
        'ž': 'z'
      }[m];
    });

    return string;
  };

  Autocomplete.prototype.highlight = function(self, suggestionValue, queryValue) {
    var suggestionString  = self.options.lookupString.call(self, suggestionValue),
        queryString       = self.options.lookupString.call(self, queryValue);

    var startIndex = suggestionString.indexOf(queryString);

    if (startIndex !== -1) {
      queryValue = suggestionValue.substring(startIndex, startIndex + queryValue.length);

      return suggestionValue.split(queryValue).join('<strong>' + queryValue + '</strong>');
    } else {
      return suggestionValue;
    }
  };

  app.Autocomplete = Autocomplete;
})(app);


/**
 *  XHR
 */
(function(app){

  function Form(selector, options) {
    var self = this;

    self.options = $.extend({
      containerSelector: '.js_partial',
      keepOverlay: false,
      resource: null,
    }, options);
    self.$form = $(selector);
    self.form = self.$form.get(0);

    self.$form.submit(function(event){
      event.preventDefault();
      self.commit();
    });
  }

  Form.prototype.commit = function() {
    var self        = this,
        $container  = self.$form.closest(self.options.containerSelector),
        data        = self.$form.data(),
        ajaxOptions = {
          type: 'POST',
          dataType: 'json',
          url: data.url || self.options.url || self.form.action
        };

    if (!$container.length) {
      $container = self.$form;
    }
    if (self.$form.hasClass('active')) {
      return false;
    }
    self.$form.addClass('active');
    self.removeErrors();

    if (typeof(self.options.beforeSend) === 'function' &&
        self.options.beforeSend.call(self, self.form) === false
    ) {
      self.$form.removeClass('active');
      return false;
    }

    $container.overlay();

    if (self.form.enctype === 'multipart/form-data' || data.files) {
      ajaxOptions.data = self.getFormData(data.files);
      ajaxOptions.processData = false;
      ajaxOptions.contentType = false;
    } else {
      ajaxOptions.data = self.$form.serializeArray();
    }

    self.addProgress();

    if (self.$progress || typeof(self.options.onProgress) === 'function') {
      ajaxOptions.xhr = function(){
        var xhr = $.ajaxSettings.xhr();

        xhr.upload.addEventListener('progress', function(event){
          if (event.lengthComputable) {
            self.onProgress(Math.round(event.loaded / event.total * 100));
          }
        });
        xhr.upload.addEventListener('load', function(event){
          if (typeof(self.options.onProgressComplete) === 'function') {
            self.options.onProgressComplete.call(self, self.form, self.$progress);
          }
        });

        return xhr;
      }
    }

    self.request = $.ajax(ajaxOptions).done(function(response) {
      if (response.error_code) {
        $container.overlay('remove');

        if (response.error) {
          app.flash.error(response.error);
        }
        if (response.errors) {
          self.addErrors(response.errors, self.options.isHint, self.options.resource);
        }

        $('.invalid', self.form).not('label').first().focus();

        if (typeof(self.options.onError) === 'function') {
          self.options.onError.call(self, self.form, response);
        }
      } else if (response.confirm) {
        $container.overlay('remove');

        if (typeof(self.options.onConfirm) === 'function') {
          self.options.onConfirm.call(self, self.form, response);
        } else {
          new app.Dialog(function(){
            self.$form.prepend('<input type="hidden" name="' + response.confirm.field_name + '" value="1">');
            self.$form.trigger('submit');
          }, {
            message: response.confirm.message,
            commit: response.confirm.commit,
            cancel: response.confirm.cancel,
            type: 'red'
          });
        }
      } else {
        if (!self.options.keepOverlay) {
          $container.overlay('remove');
        }
        if (typeof(self.options.onSuccess) === 'function') {
          self.options.onSuccess.call(self, self.form, response);
        }
      }
    }).fail(function(jqXHR, textStatus, errorThrown){
      $container.overlay('remove');
      if (jqXHR.responseJSON) {
        app.flash.error(jqXHR.responseJSON.error);
      } else if (textStatus !== 'abort') {
        app.flash.error(errorThrown);
      }
    }).always(function(jqXHR, textStatus) {
      self.$form.removeClass('active');

      if (self.$progress) {
        self.$progress.remove();
      }
    });
  };

  Form.prototype.abort = function() {
    if (self.request) {
      self.request.abort();
    }
  }

  Form.prototype.addProgress = function() {
    if (this.options.progress) {
      this.$progress = $(
        '<div class="progress">' +
          '<div class="js_progress_bar progress__bar"></div>' +
        '</div>'
      ).prependTo('body');
      this.$progressBar = this.$progress.find('.js_progress_bar');
    }
  };

  Form.prototype.onProgress = function(value) {
    if (this.$progressBar) {
      this.$progressBar.width(value + '%');
    }
    if (typeof(this.options.onProgress) === 'function') {
      this.options.onProgress.call(this, this.form, value);
    }
  };

  Form.prototype.getFormData = function(files) {
    var formData = new FormData(this.form);

    for (var name in files) {
      var data = files[name];

      formData.delete(name);

      for (var i = 0, j = data.length; i < j; i++) {
        formData.append(name, data[i]);
      }
    }

    return formData;
  };

  Form.prototype.addError = function(field, text, isHint) {
    var $field   = $(field),
        $label   = $('label[for=' + (field.id || 'unknown') + ']'),
        $wrapper = field.type === 'file' ? $field.parent() : $label,
        $parent  = $wrapper.parent(),
        $error   = $parent.find('.field-block__error');

    $field.addClass('invalid');
    $label.addClass('invalid');

    if (isHint) {
      if ($error.length) {
        $error.text(text);
      } else {
        $wrapper.after('<div class="field-block__error">' + text + '</div>');
      }
    } else {
      $field.prop('title', text);
    }
  };

  Form.prototype.addErrors = function(errors, isHint, resource) {
    var self = this;

    if (typeof(isHint) === 'undefined') {
      isHint = true;
    }

    $.each(errors, function(key, value){
      var field = resource ? self.form[resource + '[' + key + ']'] : self.form[key];

      if (resource && !field) {
        field = self.form[key];
      }

      if (!field) {
        return;
      }

      self.addError(field, value, isHint);

      field = $('#' + field.id + '_name').get(0);

      if (field) {
        self.addError(field, value, isHint);
      }
    });
  }

  Form.prototype.removeErrors = function() {
    $('#flash').remove();
    $('.invalid', this.form).removeClass('invalid').removeAttr('title');
    $('.field-block__error', this.form).remove();
  };

  Form.prototype.reset = function() {
    for (var i = 0, length = this.form.elements.length; i < length; i++) {
      if ($(this.form.elements[i]).data('reset') === false) {
        continue;
      }

      switch (this.form.elements[i].type.toLowerCase()) {
        case 'text':
        case 'password':
        case 'textarea':
        case 'hidden':
          this.form.elements[i].value = '';
          break;
        case 'radio':
        case 'checkbox':
          if (this.form.elements[i].checked) {
            this.form.elements[i].checked = false;
          }
          break;
        case 'select-one':
        case 'select-multi':
          this.form.elements[i].selectedIndex = -1;
          break;
        default:
          break;
      }
    }
  }

  app.Form = Form;

})(app);

(function(app){

  function FileUpload(file, options) {
    this.options = $.extend({
      url: null,
      tmpl: null
    }, options);

    this.upload(file);
  }

  FileUpload.prototype.upload = function(file) {
    var self        = this,
        formData    = new FormData(),
        ajaxOptions = {
          type: 'POST',
          contentType: false,
          processData: false,
          dataType: 'json',
          url: self.options.url
        };

    formData.set('file', file);

    ajaxOptions.data = formData;

    if (self.options.tmpl) {
      self.add(file);
    }

    if (typeof(self.options.onProgress) === 'function') {
      ajaxOptions.xhr = function(){
        var xhr = $.ajaxSettings.xhr();

        xhr.upload.addEventListener('progress', function(event){
          if (event.lengthComputable) {
            self.options.onProgress.call(self, Math.round(event.loaded / event.total * 100));
          }
        });

        return xhr;
      }
    }

    $.ajax(ajaxOptions).done(function(response) {
      if (response.error_code) {
        if (typeof(self.options.onError) === 'function') {
          self.options.onError.call(self, response);
        }  else {
          app.flash.error(response.error);
        }
      } else {
        if (typeof(self.options.onSuccess) === 'function') {
          self.options.onSuccess.call(self, response);
        }
      }
    }).fail(function(jqXHR, textStatus, errorThrown){
      if (typeof(self.options.onError) === 'function') {
        self.options.onError.call(self, jqXHR.responseJSON);
      } else {
        app.flash.error(jqXHR.responseJSON ? jqXHR.responseJSON.error : null);
      }
    });
  };

  FileUpload.prototype.add = function(file) {
    var self = this;

    self.$file = $(Mustache.render(self.options.tmpl, file));

    if (self.options.before) {
      self.$file.insertBefore(self.options.before);
    } else if (self.options.append) {
      self.$file.appendTo(self.options.before);
    } else if (self.options.prepend) {
      self.$file.prependTo(self.options.prepend);
    }

    self.$file.find('a').click(function(event){
      event.preventDefault

      self.$file.fadeOut('fast', function(){
        self.$file.remove();
      })
    })
  };

  FileUpload.prototype.find = function(selector) {
    return $(this.$file).find(selector);
  };

  FileUpload.prototype.replace = function(html) {
    $(this.$file).replaceWith(html);
  };

  app.FileUpload = FileUpload;

})(app);

(function(app, document){

  var resources = {};

  function Resource(name, options) {
    this.options = $.extend({
      container: null,
      filter: null,
      ident: '#' + name,
      mask: null,
      onBeforeChange: null,
      onChange: null,
      onReload: null,
      reload: null,
      url: null
    }, options);

    this.name = name;
    this.ident = this.options.ident;

    this.cleanup();
    this.push();
  }

  Resource.prototype.push = function() {
    if (!resources[this.name]) {
      resources[this.name] = {};
    }

    resources[this.name][this.ident] = this;
  };

  Resource.prototype.cleanup = function() {
    var items = resources[this.name];

    if (!items) {
      return;
    }

    for (var key in items) {
      var container = items[key].options.container;

      if (container && !$(container).length) {
        delete items[key];
      }
    };
  };

  Resource.prototype.getIdName = function() {
    if (!this.options.mask) {
      return null;
    }

    var m = this.options.mask.match(/{{(.+)}}/);

    if (m) {
      return m[1];
    }

    return null;
  };

  Resource.prototype.getId = function(data, name) {
    return data[name] || null;
  };

  Resource.prototype.getSelector = function(id) {
    if (!this.options.mask) {
      return null;
    }

    return this.options.mask.replace(/{{(.+)}}/, id);
  };

  Resource.prototype.reload = function(data) {
    var self     = this,
        params   = [],
        idName   = self.getIdName(),
        id       = self.getId(data, idName),
        selector = self.getSelector(id);

    if (typeof(self.options.reload) === 'function') {
      self.options.reload.call(self, data);

      return;
    }

    if (!self.options.url) {
      return;
    }

    if (self.options.filter) {
      params = $(self.options.filter).serializeArray();
    }

    if (id) {
      params.push({
        name: idName,
        value: id
      });
    }

    if (typeof(self.options.onBeforeChange) === 'function') {
      self.options.onBeforeChange.call(self, $(selector), data);
    }

    $.ajax({
      type: self.options.filter ? 'post' : 'get',
      url: self.options.url,
      data: params
    }).done(function(html) {
      if (typeof(self.options.onReload) === 'function') {
        self.options.onReload.call(self, $(selector), html, data);
      } else if ($(selector).length) {
        $(selector).replaceWith(html);
      } else if (self.options.container) {
        $(self.options.container).prepend(html);
      }

      if (typeof(self.options.onChange) === 'function') {
        self.options.onChange.call(self, $(selector), data);
      }
    });
  };

  function reload(name, data) {
    var items = resources[name];

    if (!items) {
      return;
    }

    for (var key in items) {
      var resource  = items[key];
          container = resource.options.container;

      if (container && !$(container).length) {
        delete items[key];
      } else {
        resource.reload(data);
      }
    };
  }

  app.Resource = Resource;
  app.reload = reload;

})(app);

app.flash = (function(){
  function message(text, options) {
    options = $.extend({
      container: document.body,
      delay: 8000,
      type: 'notice'
    }, options);

    var timer,
        $flash = $('#flash');

    if ($flash.length) $flash.remove();

    $flash = $(
      '<div id="flash" class="flash flash_' + options.type + '" style="display: none;">' +
        '<div class="flash__body">' +
          text +
        '</div>' +
      '</div>'
    ).appendTo(options.container);

    $flash.fadeIn(400, function(){
      setTimeout(function(){
        $flash.fadeOut(400, function(){
          $flash.remove();
        });
      }, options.delay);
    });

    $flash.on('click', '.flash__body', function(){
      $flash.fadeOut(400, function(){
        $flash.remove();
      });
    });
  }

  function error(text, options) {
    options = options || {};
    options['type'] = 'error';

    message(text || 'System error', options);
  }

  function notice(text, options) {
    options = options || {};
    options['type'] = 'notice';

    message(text, options, 'notice');
  }

  return {
    error: error,
    notice: notice,
    message: message
  };
}());

(function(app, document){

  function Delete(options) {
    var self = this;

    self.options = $.extend({
      container: document,
      selector: '.js_delete',
      message: 'Are you sure you want to delete this record?',
      yes: 'Ok',
      no: 'Cancel',
      type: 'red'
    }, options);

    $(self.options.container).on('click', self.options.selector, function(event){
      event.preventDefault();

      var $this = $(this),
          url   = $this.data('url') || this.href;

      if ($this.hasClass('active')) {
        return;
      }

      new app.Dialog(function(){
        $this.addClass('active');

        $.getJSON(url, function(response){
          if (response.error_code) {
            app.flash.error(response.error);
          } else if (typeof(self.options.onSuccess) === 'function') {
            self.options.onSuccess.call(self, response, $this);
          } else {
            var $tbody = $this.closest('tbody');

            $this.closest('tr').fadeOut('fast', function(){
              $(this).remove();

              if (!$tbody.find('tr').length) {
                $tbody.closest('table').remove();
              }
            });
          }
        }).fail(function (jqXHR, textStatus, errorThrown){
          if (jqXHR.responseJSON && jqXHR.responseJSON.error) {
            app.flash.error(jqXHR.responseJSON.error);
          } else {
            app.flash.error();
          }
        }).always(function(){
          $this.removeClass('active');
        });
      }, {
        message: self.options.message,
        yes: self.options.yes,
        no: self.options.no,
        type: self.options.type
      });
    });
  }

  app.Delete = Delete;

})(app, document);


app.script = (function(){

  function field(parent) {
    $('input.field_animate, textarea.field_animate', parent).each(function(){
      var $this = $(this);

      if (this.value === '') {
        $this.addClass('field_empty');
      } else {
        $this.removeClass('field_empty');
      }

      setTimeout(function(){
        $this.addClass('animate');
      }, 1);
    }).on('blur pick', function(){
      var $this = $(this);

      if (this.value === '') {
        $this.addClass('field_empty');
      } else {
        $this.removeClass('field_empty');
      }
    }).on('animationstart webkitAnimationStart', function(event){
      var $this = $(this);

      if (event.originalEvent.animationName === 'autofill') {
        $this.removeClass('field_empty');
      } else if (this.value === '') {
        $this.addClass('field_empty');
      }
    });;

    $('select.field_animate', parent).each(function(){
      var $this     = $(this),
          $selected = $(this.options[this.selectedIndex]);

      if (!$selected.text()) {
        $this.addClass('field_empty');
      }

      setTimeout(function(){
        $this.addClass('animate');
      }, 1);
    }).change(function(){
      var $this     = $(this),
          $selected = $(this.options[this.selectedIndex]);

      if ($selected.text()) {
        $this.removeClass('field_empty');
      } else {
        $this.addClass('field_empty');
      }
    });
  }

  function fileInput(selector, callback) {
    $(selector).each(function(){
      var parent  = this.parentNode,
          $input  = $(this),
          $file   = $('.field-attach__file', parent),
          $button = $('.field-attach__remove', parent),
          $delete = $($button.data('selector'));

      $input.change(function(){
        if (this.files && this.files[0]) {
          $file.text(this.files[0].name);
          $input.removeClass('field-attach__input_empty');
          $button.addClass('active');
          $delete.val(0);
        } else {
          $file.text('');
          $input.addClass('field-attach__input_empty');
          $button.removeClass('active');
          $delete.val(1);
        }

        if (typeof(callback) === 'function') {
          callback.call(this, this.files ? this.files[0] : null);
        }
      });

      if ($file.text()) {
        $input.removeClass('field-attach__input_empty');
        $button.addClass('active');
        $delete.val(0);
      } else {
        $input.addClass('field-attach__input_empty');
        $button.removeClass('active');
        $delete.val(1);
      }

      $button.click(function(event){
        event.preventDefault();

        $delete.val(1);
        $file.text('');
        $input.addClass('field-attach__input_empty');
        $button.removeClass('active');
      });
    });
  }

  function autofocus(parent) {
    var $autofocus = $('input[autofocus], textarea[autofocus], select[autofocus]', parent).first();

    if ($autofocus.is('input[type=text]')) {
      $autofocus.on('focus', function(){
        this.value = this.value;
      });
    }

    $autofocus.focus();
  }

  function overflow($modal, observe) {
    var $container = $modal.find('._modal_o');

    if ($container.length) {
      var wH = $(window).height(),
          hH = 0,
          cH = $container.outerHeight();

      $modal.find('._modal_no').each(function(){
        var $this     = $(this),
            isHidden  = $this.hasClass('hidden');

        if (isHidden) $this.removeClass('hidden');

        hH += $(this).outerHeight();

        if (isHidden) $this.addClass('hidden');
      });

      if ($container.is('iframe')) {
        $container.css({ height: Math.max(wH - 16 - hH, 100) });
      } else {
        $container.css({ maxHeight: Math.max(wH - 16 - hH, 100) });
      }

      if (observe) {
        $(window).off('resize.modal').on('resize.modal', function(){
          overflow($modal);
        });
      }
    }
  }

  function form(selector, options) {
    $(selector).each(function(){
      new app.Form(this, options);
    });
  }

  function adjustScroll(scrollable, container, $selected) {
    if (!$selected.length) return;

    var $scrollable         = $(scrollable),
        scrollableScrollTop = $scrollable.scrollTop(),
        scrollableTop       = $scrollable.offset().top,
        scrollableHeight    = $scrollable.height()
        containerTop        = $(container).offset().top - scrollableTop + scrollableScrollTop,
        selectedTop         = $selected.offset().top - scrollableTop,
        selectedHeight      = $selected.outerHeight();

    if (selectedTop < containerTop) {
      $scrollable.scrollTop(scrollableScrollTop + selectedTop - containerTop);
    } else if (selectedTop + selectedHeight > scrollableHeight) {
      $scrollable.scrollTop(scrollableScrollTop + selectedTop - scrollableHeight + selectedHeight);
    }
  }

  function infiniteScroll(parent, list, options) {
    options = $.extend({
      delta: 100,
    }, options);

    $(parent).off('scroll.infinite').on('scroll.infinite', function(){
      var $this   = $(this),
          top     = $(this).scrollTop(),
          height  = $this.height(),
          sHeight = $this.prop('scrollHeight');

      if (sHeight - top - height < options.delta) {
        var $infinite = $('.js_infinite', this);

        if (!$infinite.hasClass('active')) $infinite.trigger('click');
      }
    });

    $(list).on('click', '.js_infinite', function(event){
      event.preventDefault();

      var $this   = $(this),
          data    = {},
          filter  = $this.data('filter'),
          url     = $this.data('url') || this.href;

      if (!$this.hasClass('active')) {
        $this.addClass('active').html('please wait&hellip;');

        if (filter) {
          data = $(filter).serializeArray();
        }

        $.post(url, data, function(html){
          var $html = $(html);

          $(list).append($html);

          if (typeof(options.callback) === 'function') {
            options.callback($html);
          }

          $this.closest('.infinite, .data-infinite').remove();

        }).fail(function(jqXHR, textStatus, errorThrown){
          $this.parent().html(
            '<span class="data-infinite__anchor data-infinite__anchor_disabled">' +
              'System error' +
            '</span>'
           );
        });
      }
    });
  }

  function datepicker(parent, locale) {
    if ($.datepicker) {
      $('.field_datepicker', parent).each(function(){
        var options,
            $this = $(this),
            data  = $this.data();

        options = {
          changeMonth: true,
          changeYear: true,
          dateFormat: 'dd.mm.yy.',
          beforeShow: function(input){
            if ($(input).attr('readonly')) return false;
          },
          onSelect: function(dateText, o){
            o.input.trigger('blur');
          }
        };

        if (data.format) {
          options.dateFormat = data.format;
        }
        if (typeof(data.max) !== 'undefined') {
          options.maxDate = data.max;
        }
        if (typeof(data.min) !== 'undefined') {
          options.minDate = data.min;
        }

        $this.datepicker(options);

        $this.keydown(function(event){
          if (event.which === 13) {
            $this.datepicker('hide');
            $(this.form).trigger('submit');
          }
        });
      }).attr('autocomplete', 'off');

      if (!locale) locale = 'lv';

      $.datepicker.setDefaults($.datepicker.regional[locale]);
    }
  }

  function period(parent, periodOptions) {
    if (!$.datepicker) return;

    $('.js_period', parent).each(function(){
      var $parent = $(this),
          $inputs = $('.field_period', this),
          $start  = $inputs.first(),
          $end    = $inputs.last();

      $inputs.each(function(){
        var options,
            $this = $(this),
            data  = $this.data();

        options = $.extend({
          changeMonth: true,
          changeYear: true,
          dateFormat: 'dd.mm.yy.',
          beforeShow: function(input){
            if (input.readonly) return false;
          },
          onSelect: function(dateText, o){
            o.input.trigger('blur');
          }
        }, periodOptions);

        if ($this.data('year-range')) {
          options.yearRange = $this.data('year-range');
        }

        if (this === $start[0]) {
          if ($end.val()) {
            options.maxDate = $end.val();
          } else if (typeof(data.max) !== 'undefined') {
            options.maxDate = data.max;
          }
          if (typeof(data.min) !== 'undefined') {
            options.minDate = data.min;
          }

          options.onClose = function(selectedDate) {
            var minDate = $end.data('min');

            $end.datepicker('option', 'minDate', selectedDate || minDate);
          }
        } else {
          if ($start.val()) {
            options.minDate = $start.val();
          } else if (typeof(data.min) !== 'undefined') {
            options.minDate = data.min;
          }

          options.onClose = function(selectedDate) {
            var maxDate = $start.data('max');

            $start.datepicker('option', 'maxDate', selectedDate || maxDate);
          }
        }

        $this.datepicker(options);

        $this.keydown(function(event){
          if (event.which === 13) {
            $this.datepicker('hide');
            $(this.form).trigger('submit');
          }
        });
      }).attr('autocomplete', 'off');
    });
  }

  function resetField(element) {
    if (element.className.indexOf('js_noreset') === -1) {
      switch(element.type.toLowerCase()) {
        case 'text':
        case 'hidden':
        case 'textarea':
          element.value = '';
          break;
        case 'radio':
        case 'checkbox':
          element.checked = false;
          break;
        case 'select':
        case 'select-one':
        case 'select-multi':
          element.selectedIndex = 0;
          break;
        default:
          break;
      }
    }
  }

  function checkAll(container) {
    $(container).on('click pick', '.js_check_group_all', function(){
      $($(this).data('selector')).prop('checked', this.checked);
    });
    $(container).on('click', '.js_check_group', function(){
      if (!this.checked) {
        $($(this).data('selector')).prop('checked', false);
      }
    });
  }

  function ready(parent) {
    datepicker(parent);
    period(parent);
    field(parent);
  }

  return {
    field: field,
    fileInput: fileInput,
    autofocus: autofocus,
    overflow: overflow,
    form: form,
    datepicker: datepicker,
    period: period,
    adjustScroll: adjustScroll,
    infiniteScroll: infiniteScroll,
    resetField: resetField,
    checkAll: checkAll,
    ready: ready
  };
}());

$(document).on('click', function(event){
  var $anchors = $('.js_autohide');

  $('.js_menu_parent.active').removeClass('active');

  if ($anchors.length) {
    $anchors.each(function(){
      var $anchor    = $(this),
          $container = $($anchor.data('selector') || $anchor.prop('hash'));

      if ($anchor.is(event.target) ||
          $anchor.has(event.target).length ||
          $container.is(event.target) ||
          $container.has(event.target).length) {
      } else {
        $anchor.trigger('autohide');
      }
    });
  }
});

$(document).on('click.toggle autohide', '.js_toggle', function(event){
  event.preventDefault();

  var $this       = $(this),
      $container  = $(this.hash),
      $parent     = $this.parent().parent('.js_toggle_parent'),
      url         = $this.data('url'),
      autohide    = $this.data('autohide');

  if ($this.hasClass('toggle_loading')) return;

  if (event.type === 'autohide' || $container.is(':visible')) {
    $container.slideUp('fast', function(){
      $this.removeClass('active js_autohide');
      $parent.removeClass('active');
    });
  } else {
    if (url) {
      $this.addClass('toggle_loading');
      $.get(url, function(html){
        $this.addClass('active');
        $parent.addClass('active');
        $container.html(html);

        $this.removeClass('toggle_loading');
        $this.data('url', null);

        $container.slideDown('fast', function(){
          $container.find('[autofocus]').focus();
        });
      });
    } else {
      $this.addClass('active');
      $parent.addClass('active');
      $container.slideDown('fast', function(){
        $container.find('input, select').first().focus();
      });
    }

    if (autohide) {
      $this.addClass('js_autohide');
    }
  }
});

$('.js_modal').modal({
  selector: '.js_modal',
  beforeShow: function($parent) {
    var $uniq = $(),
        id    = $parent.children().first().prop('id');

    if (id) {
      $uniq = $('.modal [id=' + id + ']')
    }
    if ($uniq.length > 1) {
      var modal = $uniq.first().closest('.modal_data').data('modal');

      modal && modal.remove();
    }
  },
  afterShow: function(parent) {
    app.script.field(parent);
    app.script.datepicker(parent);
    app.script.period(parent);
    app.script.autofocus(parent);
    app.script.overflow(parent, true);
  }
  // ,
  // beforeRemove: function(parent) {
  //   $('input[type=text]', parent).autocomplete('dispose');
  // }
});

(function(){
  function scrolled() {
    var scroll = $(window).scrollTop();

    if (scroll > 0) {
      $('body').addClass('scrolled');
    } else {
      $('body').removeClass('scrolled');
    }
  }

  $(window).scroll(function() {
    scrolled();
  });

  scrolled();
})();

$(function(){
  app.script.ready();
});


$(document).on('click.menu', '.js_menu', function(event){
  event.preventDefault();

  var $this = $(this);

  setTimeout(function(){
    $this.parent().addClass('active');
  }, 3);
});

$(document).on('click', function(event){
  var $anchors = $('.js_autohide');

  $('.js_menu_parent.active').removeClass('active');

  if ($anchors.length) {
    $anchors.each(function(){
      var $anchor    = $(this),
          $container = $($anchor.data('selector') || $anchor.prop('hash'));

      if ($anchor.is(event.target) ||
          $anchor.has(event.target).length ||
          $container.is(event.target) ||
          $container.has(event.target).length) {
      } else {
        $anchor.trigger('autohide');
      }
    });
  }
});

$(function(){

  $('#toggle_aside').click(function(){
    var $this = $(this);

    if ($this.hasClass('active')) {
      $('#sidebar').removeClass('compact');
      $this.removeClass('active');
    } else {
      $('#sidebar').addClass('compact');
      $this.addClass('active');
    }
    
  });

});