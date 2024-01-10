define([
  "jquery",
  "underscore",
  "Backbone",
  "handlebars",
  //'libs/jquery.cookie.min',
  "libs/jquery.uniform",
  "libs/jquery.validity.min",
  "libs/jquery.validity.custom.output.login",
  "libs/jquery.tools.overlay.modified",
  "backbone/shared/activity/log"
], function(
  $,
  _,
  Backbone,
  Hb,
  //Cookie,
  Uniform,
  validity,
  validityCustom,
  Modal,
  logActivity
) {
  //HACK Due to IE not loading cookie js properly!!!
  $.cookie = function(name, value, options) {
    if (typeof value != "undefined") {
      options = options || {};
      if (value === null) {
        value = "";
        options.expires = -1;
      }
      var expires = "";
      if (
        options.expires &&
        (typeof options.expires == "number" || options.expires.toUTCString)
      ) {
        var date;
        if (typeof options.expires == "number") {
          date = new Date();
          date.setTime(date.getTime() + options.expires * 24 * 60 * 60 * 1000);
        } else {
          date = options.expires;
        }
        expires = "; expires=" + date.toUTCString();
      }
      var path = options.path ? "; path=" + options.path : "";
      var domain = options.domain ? "; domain=" + options.domain : "";
      var secure = options.secure ? "; secure" : "";
      document.cookie = [
        name,
        "=",
        encodeURIComponent(value),
        expires,
        path,
        domain,
        secure
      ].join("");
    } else {
      var cookieValue = null;
      if (document.cookie && document.cookie != "") {
        var cookies = document.cookie.split(";");
        for (var i = 0; i < cookies.length; i++) {
          var cookie = jQuery.trim(cookies[i]);
          if (cookie.substring(0, name.length + 1) == name + "=") {
            cookieValue = decodeURIComponent(cookie.substring(name.length + 1));
            break;
          }
        }
      }
      return cookieValue;
    }
  };

  var loginView = Backbone.View.extend({
    el: $("body"),

    events: {},

    isLoggedIn: require("/user/isLoggedIn"),
    getProfileRecId: require("/user/getProfileRecId"),
    completedInfo: require("/user/hasCompletedDetailedInfo"),
    tnid: require("/user/sTnid"),

    initialize: function() {
      this.render();
    },

    render: function() {
      var thisView = this;

      var loginUrl = "/user/redirect-to-cas/";

      $(function() {
        $("body").delegate("a#showTnid", "click", function(e) {
          e.preventDefault();
          thisView.showTnid();
        });
        $("body").delegate("a.showContact", "click", function(e) {
          e.preventDefault();
          thisView.showContact();
        });

        $("body").delegate(".send_enquiry_button", "click", function(e) {
          e.preventDefault();
          if (thisView.getProfileRecId !== 0) {
            $.get(
              "/supplier/log-value-event?getprofilerecid=" +
                thisView.getProfileRecId +
                "&a=BUTTON_TO_SEND_RFQ_IS_CLICKED",
              function() {
                $.cookie("tnidShow", null, { path: "/" });
                $.cookie("detShow", null, { path: "/" });
              }
            );
            window.open($(e.target).attr("href"), "_blank");
          } else {
            $.cookie("tnidShow", null, { path: "/" });
            $.cookie("detShow", null, { path: "/" });
            window.open($(e.target).attr("href"), "_blank");
          }
        });

        $("body").delegate(".tnidLink", "click", function(e) {
          $.cookie("detShow", null, { path: "/" });
          $.cookie("tnidShow", null, { path: "/" });
          $("#profile").hide();
          $("#catalogue_box").hide();
          $("#contact_content").show();
          $("#reputation_box").hide();
          $("li#contact_toggle")
            .removeClass("off")
            .addClass("on");
          $("li#profile_toggle")
            .removeClass("on")
            .addClass("off");
          $("li#catalogue_toggle")
            .removeClass("on")
            .addClass("off");
          $("li#reviews_toggle")
            .removeClass("on")
            .addClass("off");
          window.location.hash = "contact_box";
          if (map3) {
            google.maps.event.trigger(map3, "resize");
            map3.setCenter(marker3.getPosition());
          }
          $(".mainMenu").hide();
          $(".loginForm").hide();
          $(".content_wide_body_right").show();
          thisView.showTnid();
          return false;
        });

        $("body").delegate(".sendRfqBtn", "click", function(e) {
          e.preventDefault();
          $.cookie("detShow", null, { path: "/" });
          $.cookie("tnidShow", null, { path: "/" });
          if (thisView.getProfileRecId !== 0) {
            $.get(
              "/supplier/log-value-event?getprofilerecid=" +
                thisView.getProfileRecId +
                "&a=BUTTON_TO_SEND_RFQ_IS_CLICKED",
              function() {}
            );
            window.open($(e.target).attr("href"), "_blank");
          } else {
            window.open($(e.target).attr("href"), "_blank");
          }
        });

        $("body").delegate(".tnidHolder", "copy", function() {
          if (thisView.getProfileRecId !== 0) {
            $.get(
              "/supplier/log-value-event?getprofilerecid=" +
                thisView.getProfileRecId +
                "&a=TNID_IS_COPIED"
            );
          }
        });

        if (
          $.cookie("tnidShow") &&
          $.cookie("tnidShow") !== null &&
          $.cookie("tnidShow") == thisView.tnid &&
          $.cookie("loggedIn") &&
          $.cookie("loggedIn") !== null
        ) {
          $.cookie("loggedIn", null, { path: "/" });
          window.location.href = window.location + "#contact_box";
          thisView.showTnid();
        }

        if (
          $.cookie("detShow") &&
          $.cookie("detShow") !== null &&
          $.cookie("detShow") == thisView.tnid &&
          $.cookie("loggedIn") &&
          $.cookie("loggedIn") !== null
        ) {
          $.cookie("loggedIn", null, { path: "/" });
          window.location.href = window.location + "#contact_box";
          thisView.showContact();
        }
      });
    },

    showTnid: function() {
      var thisView = this;
      if (this.isLoggedIn === 1) {
        if (this.completedInfo === 0) {
          if (this.getProfileRecId !== 0) {
            $.cookie("tnidShow", null, { path: "/" });
            $.cookie("tnidShow", this.tnid, { path: "/" });
            $.cookie("fromProfile", true, { path: "/" });

            $.get(
              "/supplier/log-value-event?getprofilerecid=" +
                this.getProfileRecId +
                "&a=BUTTON_TO_VIEW_TNID_IS_CLICKED",
              function() {
                window.location.href = "/user/register-login/update";
              }
            );
          } else {
            $.cookie("tnidShow", null, { path: "/" });
            $.cookie("tnidShow", this.tnid, { path: "/" });
            $.cookie("fromProfile", true, { path: "/" });

            window.location.href = "/user/register-login/update";
          }
        } else {
          if (this.getProfileRecId !== 0) {
            logActivity.logActivity("cr-show-tnid", this.tnid);
            var thisView = this;
            $.get(
              "/supplier/log-value-event?getprofilerecid=" +
                this.getProfileRecId +
                "&a=TNID_IS_VIEWED",
              function() {
                $(".mainMenu").hide();
                $(".content_wide_body_right").show();
                if (!$.cookie("tnidShow") || $.cookie("tnidShow") === null) {
                  $.get(
                    "/supplier/log-value-event?getprofilerecid=" +
                      thisView.getProfileRecId +
                      "&a=BUTTON_TO_VIEW_TNID_IS_CLICKED"
                  );
                } else {
                  $.cookie("tnidShow", null, { path: "/" });
                }
              }
            );
          } else {
            $.cookie("tnidShow", null, { path: "/" });
            $(".mainMenu").hide();
            $(".content_wide_body_right").show();
          }
        }
      } else {
        if (this.getProfileRecId !== 0) {
          logActivity.logActivity("cr-show-tnid", this.tnid);
          var thisView = this;
          $.get(
            "/supplier/log-value-event?getprofilerecid=" +
              this.getProfileRecId +
              "&a=TNID_IS_VIEWED",
            function() {
              $(".mainMenu").hide();
              $(".content_wide_body_right").show();
              if (!$.cookie("tnidShow") || $.cookie("tnidShow") === null) {
                $.get(
                  "/supplier/log-value-event?getprofilerecid=" +
                    thisView.getProfileRecId +
                    "&a=BUTTON_TO_VIEW_TNID_IS_CLICKED"
                );
              } else {
                $.cookie("tnidShow", null, { path: "/" });
              }
            }
          );
        } else {
          $.cookie("tnidShow", null, { path: "/" });
          $(".mainMenu").hide();
          $(".content_wide_body_right").show();
        }
      }
    },

    showContact: function() {
      if (this.isLoggedIn === 1) {
        if (this.completedInfo === 0) {
          if (this.getProfileRecId !== 0) {
            $.cookie("detShow", null, { path: "/" });
            $.cookie("detShow", this.tnid, { path: "/" });
            $.cookie("fromProfile", true, { path: "/" });

            $.get(
              "/supplier/log-value-event?getprofilerecid=" +
                this.getProfileRecId +
                "&a=BUTTON_TO_VIEW_TNID_IS_CLICKED",
              function() {
                window.location.href = "/user/register-login/update";
              }
            );
          } else {
            $.cookie("detShow", null, { path: "/" });
            $.cookie("detShow", this.tnid, { path: "/" });
            $.cookie("fromProfile", true, { path: "/" });

            window.location.href = "/user/register-login/update";
          }
        } else {
          if (this.getProfileRecId !== 0) {
            logActivity.logActivity("cr-show-details", this.tnid);
            var thisView = this;
            $.get(
              "/supplier/log-value-event?getprofilerecid=" +
                this.getProfileRecId +
                "&a=CONTACT_IS_VIEWED",
              function() {
                $(".mainMenu").hide();
                $(".content_wide_body_right").show();
                if (!$.cookie("detShow") || $.cookie("detShow") === null) {
                  $.get(
                    "/supplier/log-value-event?getprofilerecid=" +
                      thisView.getProfileRecId +
                      "&a=BUTTON_TO_VIEW_CONTACT_IS_CLICKED"
                  );
                } else {
                  $.cookie("detShow", null, { path: "/" });
                }
              }
            );
          } else {
            $.cookie("detShow", null, { path: "/" });
            $(".mainMenu").hide();
            $(".content_wide_body_right").show();
          }
        }
      } else {
        if (this.getProfileRecId !== 0) {
          logActivity.logActivity("cr-show-details", this.tnid);
          var thisView = this;
          $.get(
            "/supplier/log-value-event?getprofilerecid=" +
              this.getProfileRecId +
              "&a=CONTACT_IS_VIEWED",
            function() {
              $(".mainMenu").hide();
              $(".content_wide_body_right").show();
              if (!$.cookie("detShow") || $.cookie("detShow") === null) {
                $.get(
                  "/supplier/log-value-event?getprofilerecid=" +
                    thisView.getProfileRecId +
                    "&a=BUTTON_TO_VIEW_CONTACT_IS_CLICKED"
                );
              } else {
                $.cookie("detShow", null, { path: "/" });
              }
            }
          );
        } else {
          $.cookie("detShow", null, { path: "/" });
          $(".mainMenu").hide();
          $(".content_wide_body_right").show();
        }
      }
    },

    openDialog: function() {
      $("#modal").overlay({
        mask: "black",
        left: "center",
        fixed: false,

        onBeforeLoad: function() {
          var windowWidth = $(window).width();
          var modalWidth = $("#modal").width();
          var posLeft = windowWidth / 2 - modalWidth / 2;

          $("#modal").css("left", posLeft);
        },

        onLoad: function() {
          $(window).resize(function() {
            var windowWidth = $(window).width();
            var modalWidth = $("#modal").width();
            var posLeft = windowWidth / 2 - modalWidth / 2;

            $("#modal").css("left", posLeft);
          });
        }
      });

      $("#modal")
        .overlay()
        .load();
    }
  });

  return new loginView();
});
