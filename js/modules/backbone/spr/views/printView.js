define(
  [
    "jquery",
    "underscore",
    "Backbone",
    "handlebars",
    "buyer/branches",
    "text!templates/spr/tpl/printTitle.html",
    "highcharts-defaults",
    "highcharts-defaults-print"
  ],
  function(
    $,
    _,
    Backbone,
    Hb,
    buyers,
    printTitle,
    highchartsDefaults,
    highchartsDefaultsPrint
  ) {
    var view = Backbone.View.extend({
      initialize: function() {
        this.printTitleTemplate = Handlebars.compile(printTitle);

        this.isDataLoaded = false;
        this.completedRequests = 0;

        this.on("complete", function(event, request, settings) {
          ++this.completedRequests;

          if (this.completedRequests === 2) {
            this.isDataLoaded = true;
          }
        });
      },

      render: function(runParams) {
        var view = this;

        highchartsDefaults.apply();
        highchartsDefaultsPrint.apply();

        view.selectedBuyers = buyers.buyers
          .map(function(x) {
            x.tnid = parseInt(x.tnid);

            return x;
          })
          .filter(function(x) {
            return runParams.buyers.indexOf(x.id) >= 0;
          });

        $.ajax({
          url: "/reports/data/supplier-performance-data/supplier-branches",
          type: "POST",
          data: {
            //value: $value,
            byo: runParams.buyers,
            pevMonths: 12
          }
        })
          .then(function(response) {
            view.selectedSuppliers = response.filter(function(x) {
              x.tnid = parseInt(x.tnid);

              return (
                runParams.suppliers
                  .map(function(x) {
                    x.tnid = parseInt(x.tnid);

                    return x;
                  })
                  .indexOf(x.tnid) >= 0
              );
            });
          })
          .then(function() {
            var html = view.printTitleTemplate(view);

            $("#renderContainer").prepend(html);
          });

        if (window.matchMedia) {
          // chrome & safari (ff supports it but doesn't implement it the way we need)
          var mediaQueryList = window.matchMedia("print");

          mediaQueryList.addListener(function(mql) {
            if (mql.matches) {
              reflowForPrinting();
            } else {
              reflowAfterPrinting();
            }
          });
        }

        window.addEventListener("beforeprint", function(ev) {
          reflowForPrinting();
        });

        window.addEventListener("afterprint", function(ev) {
          reflowAfterPrinting();
        });

        function reflowForPrinting() {
          if (typeof Highcharts.charts !== "undefined") {
            console.log("Resizing charts ready for printing", new Date());
            reflowTheseCharts(Highcharts.charts);
          }
        }

        function reflowAfterPrinting() {
          if (typeof Highcharts.charts !== "undefined") {
            reflowTheseCharts(Highcharts.charts);
          }
        }

        function reflowTheseCharts(charts) {
          charts.forEach(function(chart) {
            chart.reflow();
          });
        }
      }
    });

    return new view();
  }
);
