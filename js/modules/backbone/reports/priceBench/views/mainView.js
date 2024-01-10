define([
  "jquery",
  "underscore",
  "Backbone",
  "libs/jquery.tools.overlay.modified",
  "../views/filters",
  "../views/yourRowView",
  "../views/marketRowView",
  "../views/price",
  "../views/unitsOvertime",
  "../views/locations",
  "../views/recomendedSuppliers",
  "../collections/yourData",
  "../collections/marketData"
], function(
  $,
  _,
  Backbone,
  Modal,
  filters,
  yourRow,
  marketRow,
  priceView,
  overtimeView,
  locationsView,
  recomendedSuppliersView,
  yourCollection,
  marketCollection
) {
  var priceBenchView = Backbone.View.extend({
    el: $("body"),
    showMap: require("benchmark/showMap"),
    keywords: null,
    keywordList: [],
    leftPageNo: 1,
    rightPageNo: 1,
    pageSize: 50,
    dateFrom: null,
    dateTo: null,
    vessel: null,
    location: [],
    excludeLeft: [],
    excludeRight: [],
    sortLeft: "li_desc",
    sortRight: "li_desc",
    sortOrderLeft: "asc",
    sortOrderRight: "asc",
    refineLeftQuery: null,
    refineRightQuery: null,
    leftUrl: "/pricebenchmark/service/purchased/",
    rightUrl: "/pricebenchmark/service/quoted/",
    isLoading: false,
    endless: false,
    selectedTabId: 0,

    events: {
      "click a.order": "onSetOrder",
      'click input[name="submitLeft"]': "refineLeft",
      'click input[name="clearLeft"]': "clearLeft",
      'click input[name="submitRight"]': "refineRight",
      'click input[name="clearRight"]': "clearRight",
      "click .benchTab li": "benchTabClick"
    },

    initialize: function() {
      var thisView = this;
      $("body").ajaxStart(function() {
        if (!thisView.endless) {
          $("#waiting").show();
        }
      });

      $("body").ajaxStop(function() {
        if (!thisView.endless) {
          $("#waiting").hide();
        }
      });

      this.yCollection = new yourCollection();
      this.mCollection = new marketCollection();

      this.filtersView = new filters();
      this.filtersView.parent = this;

      $(window).resize(function() {
        thisView.fixHeight();
      });

      this.filtersView.getData();
    },

    getMarketData: function(e) {
      var thisView = this;

      this.mCollection.url = this.rightUrl;
      this.isLoading = true;
      this.mCollection.fetch({
        type: "POST",
        add: true,
        remove: false,
        data: $.param({
          products: this.getImpaCodeList(),
          //query: this.keywords,
          pageNo: this.rightPageNo,
          pageSize: this.pageSize,
          filter: {
            dateFrom: this.dateFrom,
            dateTo: this.dateTo,
            vessel: this.vessel,
            location: this.location,
            excludeRight: this.excludeRight,
            refineQuery: this.refineRightQuery
          },
          sortBy: this.sortRight,
          sortDir: this.sortOrderRight
        }),
        complete: function() {
          thisView.getYourData();
        }
      });
    },

    getMarketDataOnly: function(e) {
      var thisView = this;

      this.mCollection.url = this.rightUrl;
      this.isLoading = true;
      this.mCollection.fetch({
        type: "POST",
        add: true,
        remove: false,
        data: $.param({
          products: this.getImpaCodeList(),
          //query: this.keywords,
          pageNo: this.rightPageNo,
          pageSize: this.pageSize,
          filter: {
            dateFrom: this.dateFrom,
            dateTo: this.dateTo,
            vessel: this.vessel,
            location: this.location,
            excludeRight: this.excludeRight,
            refineQuery: this.refineRightQuery
          },
          sortBy: this.sortRight,
          sortDir: this.sortOrderRight
        }),
        complete: function() {
          thisView.render();
        }
      });
    },

    getYourData: function() {
      var thisView = this;

      this.yCollection.url = this.leftUrl;
      this.isLoading = true;
      this.yCollection.fetch({
        type: "POST",
        add: true,
        remove: false,
        data: $.param({
          /* impa: this.keywordList, */
          products: this.getImpaCodeList(),
          //query: this.keywords,
          pageNo: this.leftPageNo,
          pageSize: this.pageSize,
          filter: {
            dateFrom: this.dateFrom,
            dateTo: this.dateTo,
            vessel: this.vessel,
            location: this.location,
            exclude: this.excludeLeft,
            refineQuery: this.refineLeftQuery
          },
          sortBy: this.sortLeft,
          sortDir: this.sortOrderLeft
        }),
        complete: function() {
          thisView.render();
        }
      });
    },

    render: function() {
      if (this.selectedTabId === 0) {
        this.priceView = new priceView();
        this.priceView.parent = this;
        this.priceView.getData();

        $(".leftData .dataContainer .data table tbody").html("");
        $(".rightData .dataContainer .data table tbody").html("");

        this.isLoading = false;

        if (
          this.yCollection.models.length >
          0 /* && this.yCollection.models[0].attributes.stats */
        ) {
          this.renderYourItems();
        } else {
          $(".leftData .dataContainer .data table tbody").html(
            '<tr><td style="text-align: center;font-size: 14px; font-weight: bold; color: red;" colspan="9">No items found.</td></tr>'
          );
          $(".leftData .avg .value").text("N/A");
        }

        if (
          this.mCollection.models.length >
          0 /* && this.mCollection.models[0].attributes.stats */
        ) {
          this.renderMarketItems();
        } else {
          $(".rightData .dataContainer .data table tbody").html(
            '<tr><td style="text-align: center;font-size: 14px; font-weight: bold; color: red;" colspan="4">No items found.</td></tr>'
          );
          $(".rightData .avg .value").text("N/A");
        }

        $(".dataBox").show();

        var thisView = this;

        $(".leftData .dataContainer .data").scroll(function() {
          thisView.checkLeftScroll();
        });

        $(".rightData .dataContainer .data").scroll(function() {
          thisView.checkRightScroll();
        });

        this.endless = false;
      } else if (this.selectedTabId === 1) {
        //tab Units overtime

        this.overtimeView = new overtimeView();
        this.overtimeView.parent = this;
        this.overtimeView.getData();
        $(".dataBox").show();
      } else if (this.selectedTabId === 2) {
        this.recomendedSuppliersView = new recomendedSuppliersView();
        this.recomendedSuppliersView.parent = this;
        this.recomendedSuppliersView.getData();
        $(".dataBox").show();
      }

      this.fixHeight();
    },

    checkLeftScroll: function() {
      var leftLength = this.yCollection.models.length,
        pageLength = leftLength / 50,
        loadMore = pageLength === Math.floor(pageLength),
        table = $(".leftData .dataContainer .data table"),
        elem = $(".leftData .dataContainer .data"),
        triggerPoint = 100, // 100px from the bottom
        st = elem.scrollTop() + elem.height() + triggerPoint,
        th = table.height();

      if (!this.isLoading && st > th && loadMore === true) {
        this.endless = true;
        this.leftPageNo += 1; // Load next page
        this.getYourData();
      }
    },

    checkRightScroll: function() {
      var rightLength = this.mCollection.models.length,
        pageLength = rightLength / 50,
        loadMore = pageLength === Math.floor(pageLength),
        table = $(".rightData .dataContainer .data table"),
        elem = $(".rightData .dataContainer .data"),
        triggerPoint = 100, // 100px from the bottom
        st = elem.scrollTop() + elem.height() + triggerPoint,
        th = table.height();

      if (!this.isLoading && st > th && loadMore === true) {
        this.endless = true;
        this.rightPageNo += 1; // Load next page
        this.getMarketDataOnly();
      }
    },

    renderYourItems: function() {
      var str =
        Math.round(
          this.yCollection.models[0].attributes.averageUnitCost * 100
        ) / 100;
      str = str.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
      $(".leftData .avg .value").text("$ " + str);
      _.each(
        this.yCollection.models,
        function(item) {
          this.renderYourItem(item);
        },
        this
      );
    },

    renderYourItem: function(item) {
      var elem = ".leftData .dataContainer .data table tbody",
        yRow = new yourRow({
          model: item
        });

      yRow.parent = this;

      $(elem).append(yRow.render().el);
    },

    renderMarketItems: function() {
      var str =
        Math.round(
          this.mCollection.models[0].attributes.averageUnitCost * 100
        ) / 100;
      str = str.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
      $(".rightData .avg .value").text("$ " + str);

      _.each(
        this.mCollection.models,
        function(item) {
          this.renderMarketItem(item);
        },
        this
      );
    },

    renderMarketItem: function(item) {
      var elem = ".rightData .dataContainer .data table tbody",
        mRow = new marketRow({
          model: item
        });

      mRow.parent = this;

      $(elem).append(mRow.render().el);
    },

    onSetOrder: function(e) {
      e.preventDefault();
      this.refresh = true;

      var el = $(e.target);

      if ($(el).hasClass("left")) {
        this.sortLeft = $(el).attr("href");

        if (this.sortOrderLeft === "desc") {
          this.sortOrderLeft = "asc";
        } else {
          this.sortOrderLeft = "desc";
        }
      } else {
        this.sortRight = $(el).attr("href");

        if (this.sortOrderRight === "desc") {
          this.sortOrderRight = "asc";
        } else {
          this.sortOrderRight = "desc";
        }
      }

      if ($(el).hasClass("left")) {
        this.yCollection.reset();
        $(".leftData .dataContainer .data table tbody").html("");
        this.leftPageNo = 1;
        this.getYourData();
      } else {
        this.mCollection.reset();
        $(".rightData .dataContainer .data table tbody").html("");
        this.rightPageNo = 1;
        this.getMarketDataOnly();
      }
    },

    refineLeft: function(e) {
      e.preventDefault();
      this.leftPageNo = 1;
      this.refineLeftQuery = $('input[name="filterLeft"]').val();
      this.yCollection.reset();
      $(".leftData .dataContainer .data table tbody").html("");
      this.getYourData();
    },

    clearLeft: function(e) {
      e.preventDefault();
      this.leftPageNo = 1;
      $('input[name="filterLeft"]').val("");
      this.refineLeftQuery = null;
      this.yCollection.reset();
      $(".leftData .dataContainer .data table tbody").html("");
      this.getYourData();
    },

    refineRight: function(e) {
      e.preventDefault();
      this.rightPageNo = 1;
      this.refineRightQuery = $('input[name="filterRight"]').val();
      this.mCollection.reset();
      $(".rightData .dataContainer .data table tbody").html("");
      this.getMarketDataOnly();
    },

    clearRight: function(e) {
      e.preventDefault();
      this.rightPageNo = 1;
      $('input[name="filterRight"]').val("");
      this.refineRightQuery = $('input[name="filterRight"]').val();
      this.mCollection.reset();
      $(".rightData .dataContainer .data table tbody").html("");
      this.getMarketDataOnly();
    },

    benchTabClick: function(e) {
      e.preventDefault();

      var thisView = this;

      var el = $(e.target);

      var parentLi = $(el).parent();
      $(parentLi)
        .parent()
        .find("li")
        .removeClass("selected");
      $(parentLi).addClass("selected");

      this.selectedTabId = $(parentLi).index();

      thisView.render();
    },

    getImpaCodeList: function() {
      var keywordList = [];
      _.each(
        this.filtersView.impaCollection.models,
        function(item) {
          var itemData = new Object();
          itemData.impa = item.itemid;
          itemData.unit = item.selectedUnit;
          keywordList.push(itemData);
        },
        this
      );

      return keywordList;
    },

    hasImpaCodeList: function() {
      return this.filtersView.impaCollection.models[0];
    },

    fixHeight: function() {
      //fix content widht and height
      var nHeight = $("#content").height();

      if (nHeight > 0) {
        $("#body").height(nHeight + 60);
        /* if ($(".benchTab").find('li:first').hasClass('selected') == true) { */
        if (true) {
          var newWidth = $(window).width() - 260;
          if (newWidth < 980) {
            newWidth = 980;
          }
          $("#content").css("width", newWidth + "px");
        } else {
          $("#content").css("width", "auto");
        }
      }
    }
  });

  return new priceBenchView();
});
