'use strict';

define([
    'jquery',
    'underscore',
    'Backbone',
    'handlebars',
    'text!templates/buyer-connect-admin/tpl/transactionDetails.html',
    'backbone/buyer-connect-admin/models/transactionDetailsModel',
    'backbone/buyer-connect-admin/views/detailsParsedView',
    'backbone/../../lib/backbone-stickit'
], function (
    $,
    _,
    Backbone,
    Hb,
    template,
    DetailsModel,
    DetailsParsedView,
    StickIt
) {
        var View = Backbone.View.extend({
            events: {
                'click #process': 'update',
                'click #update': 'update',
                'click #mark-unfixable': 'markAsUnfixable',
                'click #view-document': 'viewDocument',
                'change :input': 'fieldChanged',
                'keyup :input': 'fieldChanged',
                'click #new-line': 'addNewLine',
                'change i.fa.fa-times': 'fieldChanged'
            },

            bindings: {
                '#transactionId': 'id',
                '#transactionDate': {
                    observe: 'transactionDate',
                    onGet: 'formatDate'
                },
                '#docType': 'docType',
                '#fileFormat': 'fileFormat',
                '#status': 'status',
                '#workflowStatus': 'workflowStatus',
                '#fileName': 'filename',
                '#configId': 'configId',
                '#remarks': 'remarks',
                '#supplierId': 'supplier.supplierId',
                '#supplierName': 'supplier.name',
                '#buyerId': 'buyer.buyerId',
                '#buyerName': 'buyer.name'
            },

            viewDocument: function () {
                window.open('/reports/data/buyer-connect/transaction/document/' + this.id, '_blank');
            },

            formatDate: function (value) {
                var date = new Date(value);

                return date.toLocaleDateString('en-GB') + ' ' + date.toLocaleTimeString('en-GB');
            },

            model: null,
            parsedModel: null,

            initialize: function (options) {
                options = options || {};

                this.id = options.id;
                this.parentView = options.parentView;

                this.model = new DetailsModel({
                    id: this.id
                });

                this.parsedView = new DetailsParsedView({
                    id: this.id,
                    detailsView: this
                });

                this.parsedModel = this.parsedView.model;

                this.isModified = false;

                this.render();

                var fieldsets = $('.loader', this.$el).map(function () {
                    return $(this).closest('fieldset')[0];
                });

                fieldsets.addClass('loading');

                this.model.fetch().then(function () {
                    fieldsets.removeClass('loading');
                });
            },

            render: function () {
                this.$el.html(template);
                this.stickit();

                this.setButtonStates();

                $('#parsedDetails', this.$el).append(this.parsedView.el);
            },

            update: function () {
                var modelObj = this.parsedModel.toJSON(),
                    parentView = this.parentView;

                if (this.isModified) {
                    modelObj.parsedData.status = 'success';
                    modelObj.parsedData.headerParsingStatus = 'success';
                    modelObj.parsedData.lineItemParsingStatus = 'success';
                } else {
                    modelObj.parsedData = null;
                }

                var json = JSON.stringify(modelObj);
                
                return $.post('/reports/data/buyer-connect/transaction?action=reprocess', json).then(function () {
                    parentView.showTransactions({
                        delayedRefresh: true
                    });
                }, function () {
                    window.alert('Server returned an error.');
                });
            },

            markAsUnfixable: function() {
                var parentView = this.parentView;

                return $.ajax({
                    url: '/reports/data/buyer-connect/transaction/' + this.id + '?action=unfixable',
                    type: 'DELETE'
                }).then(function() {
                    parentView.showTransactions();
                }, function() {
                    window.alert('Server returned an error.');
                });
            },

            setButtonStates: function() {
                var displayProcess = this.isModified === false ? 'inline-block' : 'none';
                var displayUpdate = this.isModified === false ? 'none' : 'inline-block';

                $('#process', this.$el).css({'display': displayProcess});
                $('#update', this.$el).css({'display': displayUpdate});
            },

            fieldChanged: function() {
                this.isModified = true;

                this.setButtonStates();
            },

            addNewLine: function(e) {
                this.parsedView.insertRow();

                e.preventDefault();
            }
        });

        return View;
    });
