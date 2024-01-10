(function (window, document) {
  $(document).ready(function () {
    $(".trade-rank").live("click", function (e) {
      e.preventDefault();
      openTradeRankPopUp("tradeRank", this);
    });

    $(".tradenetlink").live("click", function (e) {
      e.preventDefault();
      openTradeRankPopUp("tradeRank", this);
    });

    $(".tradenet_large_top").live("click", function (e) {
      e.preventDefault();
      openTradeRankPopUp("tradeRank", this);
    });

    $(".ss-ver-system").live("click", function (e) {
      e.preventDefault();
      openTradeRankPopUp("verSys", this);
    });

    $(".reviews-popup-link").live("click", function (e) {
      e.preventDefault();
      openTradeRankPopUp("rev", this);
    });

    $(".einvoice_icon").live("click", function (e) {
      e.preventDefault();
      eInvoicePopUp();
    });

    $("#membership_popup").live("click", function (e) {
      e.preventDefault();
      membershipPopUp();
    });

    $("span.shipserv_icon").live("click", function (e) {
      e.preventDefault();
      membershipPopUp();
    });

    $(".brandMatch-verified").live("click", function (e) {
      e.preventDefault();
      if ($(e.target).html() === "Brand Owner") {
        brandVerificationPopup();
      } else {
        verifiedPopup();
      }
    });

    $(".supplier_cell").live("click", function (e) {
      e.preventDefault();

      var id = "#membership_tooltip";
      var maskHeight = $(document).height();
      var maskWidth = $(window).width();

      //Set heigth and width to mask to fill up the whole screen
      $("#mask").css({
        width: maskWidth,
        height: maskHeight
      });

      //transition effect
      $("#mask").fadeIn(1000);
      $("#mask").fadeTo("slow", 0.0);

      //Set the popup window to center
      $(id).css("top", e.pageY - 100);
      $(id).css("left", e.pageX + 30);

      //transition effect
      $(id).fadeIn(300);
    });

    $("#resultStats").live("click", function (e) {
      e.preventDefault();

      var id = "#resultStatsModal";
      var maskHeight = $(document).height();
      var maskWidth = $(window).width();

      //Set heigth and width to mask to fill up the whole screen
      $("#mask3").css({
        width: maskWidth,
        height: maskHeight
      });

      //transition effect
      $("#mask3").fadeTo(0, 0.5);
      $("#mask3").fadeIn(1000);

      //Get the window height and width
      var winH = $(window).height();
      var winW = $(window).width();

      //Set the popup window to center
      $(id).css("top", (winH - 400) / 2);
      $(id).css("left", (winW - 500) / 2);

      //transition effect
      $(id).fadeIn(300);
    });

    //if close button is clicked
    $("a.close").live("click", function (e) {
      e.preventDefault();
      $("#mask").hide();
      $(".window").hide();
      $(".window2").hide();
      $(".window3").hide();
    });

    //if mask is clicked
    $("#mask").live("click", function (e) {
      $(this).hide();
      $(".window").hide();
      $(".window2").hide();
      $(".window3").hide();
      $("#traderankTooltip").hide();
      $("#traderankTooltip").html("");
    });

    $("#rollover_big").live("click", function (e) {
      $(this).hide();
      $("#mask").hide();
      $("#mask3").hide();
    });
  });

  function displayPopupWindow(id, tooltipName, boxWidth) {
    var bodyEl = $("body");

    if ($("#mask").length === 0) {
      bodyEl.append('<div id="mask"></div>');
    }

    if ($(id).length === 0) {
      bodyEl.append('<div id="' + tooltipName + '"></div>');
    }

    if (typeof bodyEl == "undefined") {
      bodyEl = $("body");
      bodyEl.append('<div id="' + tooltipName + '"></div>');
    }

    //Get the screen height and width
    var maskHeight = $(document).height();
    var maskWidth = $(window).width();

    //Set heigth and width to mask to fill up the whole screen
    $("#mask").css({
      width: maskWidth,
      height: maskHeight
    });

    //transition effect
    $("#mask").fadeIn("fast");
    $("#mask").fadeTo("fast", 0.8);

    //Set the popup window to center
    $(id).css("position", "fixed");
    $(id).css("z-index", "10700");
    $(id).css("width", boxWidth + "px");
    $(id).css(
      "top",
      Math.round($(window).height() / 2 - $(id).height() / 2) + "px"
    );
    $(id).css(
      "left",
      Math.round($(window).width() - $(id).width()) / 2 +
      $(window).scrollLeft() +
      "px"
    );
  }

  function openBrandInfoCompletionForm(brandId) {
    alert("opening form for brandId: " + brandId);
    $(".window").hide();

    var id = "#brand_invitation_form_popup";
    var bodyEl = $("body");

    if ($("#mask").length === 0) {
      bodyEl.append('<div id="mask"></div>');
    }

    if ($(id).length === 0) {
      bodyEl.append('<div id="traderankTooltip"></div>');
    }

    //Get the screen height and width
    var maskHeight = $(document).height();
    var maskWidth = $(window).width();

    //Set heigth and width to mask to fill up the whole screen
    $("#mask").css({
      width: maskWidth,
      height: maskHeight
    });

    //transition effect
    $("#mask").fadeIn(500);
    $("#mask").fadeTo("slow", 0.8);

    //Set the popup window to center
    $(id).css("position", "fixed");
    $(id).css(
      "top",
      Math.round($(window).height() / 2 - $(id).height() / 2) + "px"
    );
    $(id).css(
      "left",
      Math.round($(window).width() - $(id).width()) / 2 +
      $(window).scrollLeft() +
      "px"
    );

    $.get("/supplier/invite-brand-owner-form/brandId/" + brandId, function (
      data
    ) {
      $(id).html(data);
    });

    //transition effect
    $(id).fadeIn(300);
  }

  function openTradeRankPopUp(tabIdent, el) {
    $(".window").hide();

    var id = "#traderankTooltip";
    displayPopupWindow(id, "traderankTooltip", 700);

    var idArray = $(el)
      .attr("id")
      .split("-");
    var tnid = parseInt(idArray[1]);

    $.get(
      "/supplier/traderank-tooltip/tab/" + tabIdent + "/tnid/" + tnid,
      function (data) {
        $(id).html(data);
      }
    );

    //transition effect
    $(id).fadeIn(300);
  }

  function eInvoicePopUp() {
    $(".window").hide();

    var id = "#einvoice_tooltip";
    displayPopupWindow(id, "einvoice_tooltip", 550);

    $.get("/supplier/einvoice-tooltip", function (data) {
      $(id).html(data);
    });

    //transition effect
    $(id).fadeIn(300);
  }

  function membershipPopUp() {
    $(".window").hide();

    var id = "#membership_tooltip";
    displayPopupWindow(id, "membership_tooltip", 550);

    $.get("/supplier/membership-tooltip", function (data) {
      $(id).html(data);
    });

    //transition effect
    $(id).fadeIn(300);
  }

  function brandVerificationPopup() {
    $(".window").hide();

    var id = "#einvoice_tooltip";
    displayPopupWindow(id, "einvoice_tooltip", 550);

    $.get("/supplier/brandverification-tooltip", function (data) {
      $(id).html(data);
    });

    //transition effect
    $(id).fadeIn(300);
  }

  function verifiedPopup() {
    $(".window").hide();
    var id = "#einvoice_tooltip";
    displayPopupWindow(id, "einvoice_tooltip", 550);

    $.get("/supplier/verified-tooltip", function (data) {
      $(id).html(data);
    });

    //transition effect
    $(id).fadeIn(300);
  }


})(window, document);

function maxSuppliersPopUp(maxValue) {
  $('.window').hide();

  //Get the A tag
  var id = "#maxsuppliers_tooltip";
  var bodyEl = $('body');

  if ($('#mask').length === 0) {
    bodyEl.append('<div id="mask"></div>');
  }

  if ($(id).length === 0) {
    bodyEl.append('<div id="maxsuppliers_tooltip"></div>');
  }

  if (typeof bodyEl == "undefined") {
    bodyEl = $('body');
    bodyEl.append('<div id="maxsuppliers_tooltip"></div>');
  }

  //Get the screen height and width
  var maskHeight = $(document).height();
  var maskWidth = $(window).width();

  //Set heigth and width to mask to fill up the whole screen
  $('#mask').css({
    'width': maskWidth,
    'height': maskHeight
  });

  //transition effect
  $('#mask').fadeIn("fast");
  $('#mask').fadeTo("fast", 0.8);

  //Set the popup window to center
  $(id).css("position", "fixed");
  $(id).css("z-index", "10700");
  $(id).css("width", "550px");
  $(id).css("height", "330px");
  $(id).css("top", (Math.round($(window).height() / 2 - $(id).height() / 2)) + "px");
  $(id).css("left", (Math.round($(window).width() - $(id).width()) / 2 + $(window).scrollLeft()) + "px");

  var data = '<div id="topToolBar" style="height:40px;">' +
    '		<div id="closeTooltipButton"></div>' +
    '		<div class="clear"></div>' +
    '</div>' +
    '<div id="maxsuppliers_body">' +
    '    <div class="zz header">' +
    '        <h2>Too many suppliers selected</h2>' +
    '    </div>' +
    '    <div class="cont">' +
    '       <p>Sorry, you can only select up to [--maxValue--] Suppliers.</p>' +
    '       <p>If you send the same RFQ to more than [--maxValue--] suppliers, in 1 or more batches, the system will permanently disable your email address.</p>' +
    '    </div>' +
    '</div>' +
    '<script type="text/javascript">' +
    '	$(document).ready(function(){' +
    '		$(\'#closeTooltipButton\').click(function (e) {' +
    '			e.preventDefault();' +
    '			$(\'#mask\').hide();' +
    '			$(\'#maxsuppliers_tooltip\').hide();' +
    '			$(\'#maxsuppliers_tooltip\').html(\'\');' +
    '		});' +
    '        $(\'#mask\').click(function (e) {' +
    '			e.preventDefault();' +
    '			$(\'#maxsuppliers_tooltip\').hide();' +
    '			$(\'#maxsuppliers_tooltip\').html(\'\');' +
    '		});' +
    '' +
    '	});' +
    '</script>';

  data = data.replace(/\[--maxValue--\]/g, maxValue);
  $(id).html(data);
  $(id).fadeIn("fast");
}