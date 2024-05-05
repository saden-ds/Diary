(function($) {
  var defaults = {
      url: false,
      opener: false,
      className: 'modal',
      content: '',
      data: {},
      scrollable: false,
      scrollbar: false,
      modal: true,
      moveable: true,
      overlay: true,
      view: 'default',
      tools: true,
      gravity: false,
      cancelText: 'Cancel',
      confirmText: 'Confirm',
      windowLocation: false,
      closeOnEsc: true,
      beforeShow: function(){},
      afterShow: function(){},
      beforeRemove: function(){}
  };

  var methods = {
    init: function(opener) {
      var data = {};

      if (opener) this.opener = opener;

      if (this.opener) {
        data = $(this.opener).data();

        $(this.opener).addClass('active');

        if (data.view) {
          this.view = data.view;
        }

        if (data.tools || data.tools === false) {
          this.tools = data.tools;
        }
      } else {
        this.gravity = false;
      }

      if (this.url === false && this.content === '') {
        this.url = data.url || this.opener.href || false;
      }

      if (this.$modal && this.$modal.is(':visible')) {
        this.remove();
      } else {
        this.create();
      }
    },

    success: function() {
      this.beforeShow.call(this, this.$content);
      this.$modal.css({ visibility: 'visible' });
      this.position();
      this.afterShow.call(this, this.$content);
    },

    position: function() {
      var $opener = $(this.opener),
          $parent = $opener.parent(),
          pos     = {},
          opener  = $.extend({}, $opener.offset(), {
                      width: $opener.outerWidth(),
                      height: Math.min($parent.height(), $opener.outerHeight())
                    }),
          modal   = {};

      this.$modal.addClass(this.className + '_' + this.gravity);

      modal = { width: this.$modal.outerWidth(), height: this.$modal.outerHeight() };

      if (this.gravity) {
        switch (this.gravity.charAt(0)) {
          case 's':
            pos = { top: opener.top + opener.height, left: opener.left + opener.width / 2 - modal.width / 2 };
            break;
          case 'n':
            pos = { top: opener.top - modal.height, left: opener.left + opener.width / 2 - modal.width / 2 };
            break;
          case 'w':
            pos = { top: opener.top + opener.height / 2 - modal.height / 2, left: opener.left - modal.width };
            break;
          case 'e':
            pos = { top: opener.top + opener.height / 2 - modal.height / 2, left: opener.left + opener.width };
            break;
          }

        if (this.gravity.length == 2) {
            if (this.gravity.charAt(1) == 'w') {
                pos.left = opener.left;

                this.$modal.find('.' + this.className + '__gravity').css({ left: opener.width / 2 });
            } else {
                pos.left = opener.left + opener.width - modal.width;

                this.$modal.find('.' + this.className + '__gravity').css({ right: opener.width / 2 });
            }
        }

        this.$modal.css(pos);
      }
    },

    move: function() {
      if (this.moveable) {
        var self  = this,
            delta = 64,
            x     = 0,
            y     = 0;
        self.$header = self.$window.find('._modal_holder');
        self.$header.on('mousedown.modal', function(downEvent){
          var offset      = self.$window.offset(),
              left        = offset.left - x,
              top         = offset.top - y,
              width       = self.$window.width(),
              height      = self.$window.height(),
              areaWidth   = $(window).width(),
              areaHeight  = $(window).height(),
              startX      = downEvent.pageX - x,
              startY      = downEvent.pageY - y;

          if (downEvent.target !== this) return;

          self.$header.addClass('move');

          $(document).on('mousemove.modal', function(moveEvent){
            x = moveEvent.pageX - startX;
            y = moveEvent.pageY - startY;

            if (left + width + x < delta) {
              x = -left - width + delta;
            } else if (left + x > areaWidth - delta) {
              x = areaWidth - left - delta;
            }
            if (top + y < 0) {
              y = -top;
            } else if (top + y > areaHeight - delta) {
              y = areaHeight - top - delta;
            }

            self.$window.css({
              '-webkit-transform':  'translate(' + x + 'px, ' + y + 'px)',
              '-moz-transform':     'translate(' + x + 'px, ' + y + 'px)',
              '-ms-transform':      'translate(' + x + 'px, ' + y + 'px)',
              '-o-transform':       'translate(' + x + 'px, ' + y + 'px)',
              'transform':          'translate(' + x + 'px, ' + y + 'px)'
            });
          });

          return false;
        });
        self.$header.on('mouseup.modal', function(event){
          self.$header.removeClass('move');
        });
      }
    },

    create: function(){
      var self      = this,
          template  = '',
          $opener   = $(self.opener);

      if (self.gravity === false) {
        template =
          '<table class="modal modal_data ' + self.view + ( self.scrollable ? ' modal_scrollable' : '') + '">' +
            '<tr>' +
              '<td class="modal__container">' +
                '<table class="modal__wrapper">' +
                  '<tr>' +
                    '<td>' +
                      '<div class="modal__window">' +
                        '<div class="modal__tools">' +
                          '<a class="js_modal_close modal__close" href="#close"></a>' +
                        '</div>' +
                        '<div class="js_partial modal__body"></div>' +
                      '</div>' +
                    '</td>' +
                  '</tr>' +
                '</table>' +
              '</td>' +
            '</tr>' +
          '</table>';
      } else {
        template =
          '<div class="modal modal_data ' + self.view + '">' +
            '<div class="modal__gravity"></div>' +
            '<div class="modal__window">' +
              '<div>' +
                '<div class="modal__tools">' +
                  '<a class="js_modal_close modal__close" href="#close"></a>' +
                '</div>' +
                '<div class="js_partial modal__body"></div>' +
              '</div>' +
            '</div>' +
          '</div>';
      }

      self.$modal = $(template.split('modal').join(self.className)).css({ visibility: 'hidden' });

      if (self.overlay) {
        self.$overlay = $('<div class="' + self.className + '-overlay"></div>').appendTo('body');
      }

      self.$modal
        .appendTo('body')
        .data('modal', this);

      if (self.gravity === false) {
        self.$modal.css({ top: $(window).scrollTop() });
      } else {
        $(window).on('resize.modal', function(){ self.position(); });
      }

      if (self.tools) self.$modal.find('.' + self.className + '__tools').show();

      self.$window = self.$modal.find('.' + self.className + '__window');
      self.$content = self.$modal.find('.' + self.className + '__body');

      self.$modal
        .on('click.modal-close', '.js_' + self.className + '_close', function(event){
          self.remove();
          event.preventDefault();
        })
        .on('click.modal-confirm', '.js_' + self.className + '_confirm', function(){
          $opener.data('confirmed', true);
          self.remove();
          $opener.click().data('confirmed', false);
          event.preventDefault();

          if (self.windowLocation === true) window.location.href = self.opener.href;
        });

      if (!self.modal) {
        self.docevnt = function(event){
          if (!($(self.opener).is(event.target) || $(self.opener).has(event.target).length || self.$window.is(event.target) || self.$window.has(event.target).length)) {
            self.remove();
          }
        };
        setTimeout(function(){
          if (self.gravity === false) {
            self.$modal.on('click.modal-close', self.docevnt);
          } else {
            $(document).on('click.modal-close', self.docevnt);
          }
        }, 0);
      }

      if (self.closeOnEsc && !self.modal) {
        $(document).off('keyup.modal-close').on('keyup.modal-close', function(event) {
          if (event.keyCode === 27) {
            self.remove();
          }
        });
      }

      self.load();

      if (!self.scrollbar) {
        $('html').addClass('modal-disable-scroll');
      }
    },

    load: function() {
      var self    = this,
          $opener = $(this.opener);

      if (self.content !== '') {
        self.$content.html(self.content);
        self.success();
        self.move();
      } else if ($opener.data('confirm')) {
        self.$content.html(
          '<div class="' + self.className + '__message">' + $opener.data('confirm') + '</div>' +
          '<div class="' + self.className + '__actions">' +
            '<input type="button" value="' + self.confirmText + '" class="' + self.className + '__commit">' +
            '<a href="#close" class="' + self.className + '__cancel">' + self.cancelText + '</a>' +
          '</div>'
        );
        self.success();
      } else if (self.url) {
        $.ajax({
          type: 'get',
          url: self.url,
          data: self.data,
          success: function(html) {
            self.$content.html(html);
            self.success();
            self.move();
          },
          error: function(jqXHR, textStatus, errorThrown) {
            if ($.trim(jqXHR.responseText)) {
              self.$content.html(jqXHR.responseText);
              self.success();
              self.move();
            } else {
              self.remove();
            }
          }
        });
      } else {
        self.success();
        self.move();
      }
    },

    reload: function() {
      this.load();
    },

    remove: function() {
      this.beforeRemove.call(this, this.$content);

      this.$modal.remove();
      if (this.$overlay) this.$overlay.remove();

      $(this.opener).removeClass('active');

      if (!this.scrollbar && !$('.modal_data').length) {
        $('html').removeClass('modal-disable-scroll');
      }
    }
  };

  $.modal = function(options) {
    var modal = $.extend({}, defaults, methods, options);

    modal.init();

    return modal;
  };

  $.fn.modal = function(options) {
    var $selector = options.selector ? $(document) : this;

    $selector.on('click.modal', options.selector, function(event) {
      var $this = $(this),
          modal = $this.data('modal');

      if ($this.data('confirmed') !== true) {
        if (!modal) {
          modal = $.extend({}, defaults, methods, options);
          $this.data('modal', modal);
        }

        modal.init(this);

        event.preventDefault();
      }
    });

    $(document).on('mouseup.modal', function(){
      $(this).off('mousemove.modal');
    });

    return this;
  };
})(jQuery);