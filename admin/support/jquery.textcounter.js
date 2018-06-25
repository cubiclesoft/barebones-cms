// jQuery plugin to display a character count in text boxes.
// (C) 2017 CubicleSoft.  All Rights Reserved.

(function($) {
	$.fn.TextCounter = function(options) {
		this.each(function() {
			var $this = $(this);

			// Remove event handlers.
			$this.off('keydown.textcounter');
			$this.off('keyup.textcounter');
			$this.off('change.textcounter');

			// Remove created element (if any).
			if ($this.data('textcountertarget') && typeof($this.data('textcountertarget')) === 'object')
			{
				$this.data('textcountertarget').remove();
				$this.removeData('textcountertarget');
			}
		});

		if (typeof(options) === 'string' && options === 'destroy')  return this;

		var settings = $.extend({ 'target' : null }, $.fn.TextCounter.defaults, options);

		return this.each(function() {
			var $this = $(this);
			var dest = (settings.target === null ? $this.append($('<div>')) : $(settings.target));

			if (settings.target === null)  $this.data('textcountertarget', dest);

			var CounterHandler = function(e) {
				var val = $this.val();
				var vallen = (settings.unit === 'words' ? val.split(/\s+/).length : val.length);
				var valid = (settings.limit == 0 || vallen <= settings.limit);

				dest.removeClass(settings.okayClass).removeClass(settings.errorClass);
				dest.addClass(valid ? settings.okayClass : settings.errorClass);
				dest.html((valid ? '' : settings.errorMsg + '  ') + (vallen == 1 ? settings.mainMsgOne : settings.mainMsg).replace('{x}', vallen).replace('{y}', settings.limit));
			};

			$this.on('keydown.textcounter', CounterHandler).on('keyup.textcounter', CounterHandler).on('change.textcounter', CounterHandler).change();
		});
	}

	$.fn.TextCounter.defaults = {
		'limit' : 0,
		'unit' : 'characters',
		'okayClass' : 'textcounter_okay',
		'errorClass' : 'textcounter_error',
		'mainMsg' : '{x} of {y} characters entered.',
		'mainMsgOne' : '{x} of {y} characters entered.',
		'errorMsg' : 'Too many characters entered.'
	};
}(jQuery));
