/**
 * Format entered numeric values to decimals and string
 * added 30/03/2015
 */

//as IE11 does not support repeat string function let's add it if this is not supported by default

if (!String.prototype.repeat) {
  String.prototype.repeat = function(count) {
    "use strict";
    if (this == null) {
      throw new TypeError("can't convert " + this + " to object");
    }
    var str = "" + this;
    count = +count;

    if (count < 0) {
      throw new RangeError("repeat count must be non-negative");
    }

    if (count == Infinity) {
      throw new RangeError("repeat count must be less than infinity");
    }

    count |= 0;

    if (str.length == 0 || count == 0) {
      return "";
    }

    if (str.length * count >= 1 << 28) {
      throw new RangeError(
        "repeat count must not overflow maximum string size"
      );
    }

    while ((count >>= 1)) {
      str += str;
    }

    str += str.substring(0, str.length * count - str.length);
    return str;
  };
}

$(document).ready(function() {
  if ($("#TotalCost").length > 0) {
    var inputValue = $("#TotalCost").val();
    var stringToFormat = inputValue.replace(/[^\d.-]/g, "");
    var formattedNr = formatMoney(stringToFormat);

    $("#BillingCurrency").val($("#currencies").val());

    if (formattedNr != $("#TotalCost").val()) {
      $("#TotalCost").val(formattedNr);
    }

    $("#InvoiceId").blur(function() {
      $("#invoice").val($("#InvoiceId").val());
    });

    $("#TotalCost").blur(function() {
      var inputValue = $("#TotalCost").val();
      var stringToFormat = inputValue.replace(/[^\d.-]/g, "");
      var formattedNr = formatMoney(stringToFormat);
      var formattedNrToSage = formatMoney(stringToFormat, 2, ".", "");
      if (formattedNr != $("#TotalCost").val()) {
        $("#TotalCost").val(formattedNr);
      }
      $("#totalTransaction").val(formattedNrToSage);
    });

    $("#TotalCost").focus(function() {
      var inputValue = $("#TotalCost").val();
      var stringToFormat = inputValue.replace(/[^\d.-]/g, "");
      var formattedNrWoSep = formatMoney(stringToFormat, 2, ".", "");
      if (formattedNrWoSep != inputValue) {
        $("#TotalCost").val(formattedNrWoSep);
      }
    });

    $("#TotalCost").click(function() {
      $(this).select();
    });

    $("#currencies").blur(function() {
      $("#BillingCurrency").val($("#currencies").val());
    });

    $("#BillingCountry").change(function() {
      var selectedCountry = $(this).val();
      var billingStateSelector = $("#BillingState");

      if (selectedCountry === "US") {
        billingStateSelector.removeAttr("disabled");
      } else {
        billingStateSelector.val("");
        billingStateSelector.attr("disabled", "disabled");
      }
    });
  }

  $('input[name="InvoiceId"]').blur(function(){
    var input = $(this).val();
    var validationElement = $('.invoiceIdValidation');
    
    if (input.length < 6) {
      validationElement.show().delay(1500).fadeOut(100);
    } else {
      validationElement.hide();
    }
  });

  // Restrict input to digits by using a regular expression filter. (This is now deprecated)
  // $('input[name="InvoiceId"]').inputFilter(function(value) {
  //   /*
  //    * Replace return true to this one if you want to disable alpha input
  //    * return /^[\d|_]*$/.test(value);
  //    * 
  //    * Replace return true to this one to allow only numbers letters and some charaters like comma, dot...
  //    * return /^([a-zA-Z0-9 _\.,/-]+)$/.test(value);
  //    */
  //   return true;
  // });
});

function formatMoney(n, c, d, t) {
  var c = isNaN((c = Math.abs(c))) ? 2 : c,
    d = d == undefined ? "." : d,
    t = t == undefined ? "," : t,
    s = n < 0 ? "-" : "",
    i = parseInt((n = Math.abs(+n || 0).toFixed(c))) + "",
    j = (j = i.length) > 3 ? j % 3 : 0;
  return (
    s +
    (j ? i.substr(0, j) + t : "") +
    i.substr(j).replace(/(\d{3})(?=\d)/g, "$1" + t) +
    (c
      ? d +
        Math.abs(n - i)
          .toFixed(c)
          .slice(2)
      : "")
  );
}
