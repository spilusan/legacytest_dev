// Restricts input for each element in the set of matched elements to the given inputFilter.
(function($) {
  $.fn.inputFilter = function(inputFilter) {
    return this.on(
      "input keydown keyup mousedown mouseup select contextmenu drop",
      function() {
        if (inputFilter(this.value)) {
          var cleanedValue  = this.value.replace(/_/g, '');
          var digitGuideCount = (10 - cleanedValue.length > 0) ? 10 - cleanedValue.length : 0;
          this.oldSelectionStart = this.selectionStart;
          this.oldSelectionEnd = this.selectionEnd;
          var newValue = cleanedValue + "_".repeat(digitGuideCount);
          this.value = newValue.substring(0, 10);
          this.oldValue = this.value;
          this.setSelectionRange(this.oldSelectionStart, this.oldSelectionEnd);
        } else if (this.hasOwnProperty("oldValue")) {
          this.value = this.oldValue;
          this.setSelectionRange(this.oldSelectionStart, this.oldSelectionEnd);
        }
      }
    );
  };
})(jQuery);
