define([
    'jquery',
    'underscore',
    'Backbone',
    'handlebars',
    'text!templates/buyer-connect-admin/tpl/transactionParsed.html',
    '/js/modules/backbone/buyer-connect-admin/models/transactionParsedModel.js',
    'backbone/lib/jquery-ui-1.10.3/tabs'
], function(
    $,
    _,
    Backbone,
    Hb,
    template,
    ParsedModel
) {
    var View;
    View = Backbone.View.extend({
        events: {},
        bindings: {},
        lineItems: [],
        lineItemHeaders: [],
        lineItemsTable: null,
        lineItemsContainer: null,
        confirmMessage: 'Delete this line item?',
        separator: "-",
        tabNameField: 'Type',
        defaultPanelTitle: 'Party',
        partyPanelSelector: '.party-section',
        tabIdPrepend: 'tab_',

        initialize: function(options) {
            var view = this;
            options = options || {};

            this.id = options.id;
            this.detailsView = options.detailsView;

            this.model = new ParsedModel({id: this.id});

            this.render();

            var fieldsets = $('.loader', this.$el).map(function() {
                return $(this).closest('fieldset')[0];
            });

            fieldsets.addClass('loading');

            this.model.fetch().then(function() {
                fieldsets.removeClass('loading');

                view.headers = view.model.attributes.parsedData.headers || {};
                view.parties = view.model.attributes.parsedData.parties || [];
                view.lineItems = view.model.attributes.parsedData.lineItemRecords;
                view.lineItemHeaders = view.getLineItemHeaders(view.model.attributes.parsedData.columnDefinitions);

                view.renderParsedData.call(view);
            });
        },

        render: function() {
            this.$el.empty().html(template);
            this.stickit();
        },

        renderParsedData: function() {
            $('#documentStatus', this.detailsView.$el).text(this.model.attributes.parsedData.status);
            $('#headersStatus', this.$el).text(this.model.attributes.parsedData.headerParsingStatus);
            $('#lineItemsStatus', this.$el).text(this.model.attributes.parsedData.lineItemParsingStatus);

            this.renderHeaderFields();
            this.renderPartyFields();

            this.renderLineItems();
        },

        renderHeaderFields: function() {
            var view = this,
                $el = this.$el,
                fields = this.model.attributes.parsedData.headers;

            $.each(fields || [], function(key, value) {
                var fieldDiv = $('<div class="gr-6 gr-12@mobile" />'),
                    field = $('<div class="field" />').appendTo(fieldDiv),
                    labelText = view.splitCamelCasedLabel(key),
                    fieldId = 'header_' + key;

                $('<label />').text(labelText).appendTo(field);

                if (view.getFieldTypeByName(key)) {
                    $('<textarea class="input-control" />')
                        .prop('id', fieldId)
                        .html(value)
                        .appendTo(field);
                } else {
                    $('<input class="input-control" type="text" />')
                        .prop('id', fieldId)
                        .val(value)
                        .appendTo(field);
                }

                $('#headerFields', $el).append(fieldDiv);

                view.addBinding(null, '#' + fieldId, 'parsedData.headers.' + key);
            });
        },

        renderPartyFields: function() {
            var view = this,
                bindingKey = 'parsedData.parties.',
                $partySection = $(view.partyPanelSelector);

            if (this.parties.length > 0) { $partySection.toggle(); }

            $.each(this.parties, function(tabIndex, party) {
                view.createTab(tabIndex, party['Type']);
                bindingKey = bindingKey + tabIndex;

                view.setTabData(tabIndex, party, bindingKey);
                bindingKey = bindingKey.replace(tabIndex, '');
            });

            $('#partyFields').tabs();
        },

        createTab: function(tabIndex, name) {
            var tabId = 'tab_' + tabIndex,
                $el = this.$el,
                $partyField = $("#partyFields", $el);

            $partyField.find("ul").append(
                $('<li>').append(
                    $('<a>').attr('href', '#' + tabId).append(
                        $('<span>').append(this.splitCamelCasedLabel(name))
                    )
                )
            );

            $partyField.append(
                $("<div />").attr("id", tabId)
            );
        },

        setTabData: function(tabIndex, partyData, bindingKey, title) {
            var view = this,
                $el = this.$el,
                tabId = this.tabIdPrepend + tabIndex,
                divId = '#' + tabId,
                row = $('<div />').addClass('row').appendTo($(divId, $el)),
                tabTitle = title || this.defaultPanelTitle,
                fieldId = 'party_' + tabId;

            if ($.isEmptyObject(partyData)) { return true; }

            view.appendPanelTo(row, tabTitle);

            $.each(partyData, function(fieldName, value) {
                var fieldDiv = $('<div class="gr-6 gr-12@mobile" />'),
                    field = $('<div class="field" />').appendTo(fieldDiv),
                    labelText = view.splitCamelCasedLabel(fieldName);

                fieldId = fieldId + "_" + fieldName;
                
                if (view.isTabTypeToSkip(fieldName)) { return true; }

                $('<label></label>').text(labelText).appendTo(field);

                if (view.isNewPanel(value)) {
                    var index = tabId.split("_")[1];
                    
                    bindingKey = bindingKey + '.Contact';

                    view.setTabData(index, value, bindingKey, fieldName);
                } else {
                    view.processSingleLineItem(value, field, fieldId);
                    row.append(fieldDiv);
                }

                view.addBinding(null, '#' + fieldId, bindingKey + '.' + fieldName);
                fieldId = fieldId.replace("_" + fieldName, '');
            });

            $(divId, $el).append(row);
        },

        isNewPanel: function(value) {
            return value instanceof Object;
        },

        isTabTypeToSkip: function(value) {
            return value === this.tabNameField;
        },

        appendPanelTo: function(row, title) {
            row.append(
                $('<div class="panel panel-default" />').append(
                    $('<div class="panel-heading" />').append(
                        $('<h3 class="panel-title" />').text(title)
                    )
                ).append(
                    $('<div class="panel-body" />')
                )
            );
        },

        splitCamelCasedLabel: function(label) {
            return label.replace(/([A-Z])/g, ' $1').trim();
        },

        processSingleLineItem: function(value, field, fieldId) {
            var $input = this.hasMultipleLines(value) ?
                $('<textarea class="input-control" />') :
                $('<input class="input-control" type="text" />');

            $input
                .prop('id', fieldId)
                .val(value)
                .appendTo(field);
        },

        hasMultipleLines: function(value) {
            return value.indexOf('\n') !== -1;
        },

        renderLineItems: function() {
            this.lineItemsContainer = $('#lineItems', this.$el);

            this.createLineItemsTableStructure();

            this.renderLineItemsTableHeader();
            this.renderLineItemsTableBody();

            this.lineItemsTable.appendTo(this.lineItemsContainer);
        },

        createLineItemsTableStructure: function() {
            this.lineItemsTable = $("<table id='line-items-table' />");
        },

        renderLineItemsTableHeader: function() {
            var view = this,
                thead = $('<thead />').appendTo(this.lineItemsTable),
                headTr = $('<tr />').appendTo(thead);

            $.each(this.lineItemHeaders, function(index, fieldName) {
                var thText = view.splitCamelCasedLabel(fieldName);

                $('<th />').text(thText).appendTo(headTr);
            });

            $('<th class="text-center" />').text('#').appendTo(headTr);
        },

        renderLineItemsTableBody: function() {
            var tbody = $('<tbody />');

            this.lineItemsTable.find('tbody').empty();
            tbody.appendTo(this.lineItemsTable);

            this.addRows(tbody, this.lineItems, this.lineItemHeaders);

            this.attachEventsToCells(this.lineItemsContainer, this.model);

            this.lineItemsTable.appendTo(this.lineItemsContainer);
        },

        addRows: function(tbody, lineItems, fields) {
            var view = this;

            $.each(lineItems, function(a, item) {
                var tr = $('<tr />').appendTo(tbody);

                view.addCells(fields, item, tr, a);
                $('<td class="text-center no-bind" />').html('<a href="#" class="delete-line"><i class="fa fa-times" /></a>').appendTo(tr);
            });
        },

        addCells: function(fields, item, tr, a) {
            var view = this;

            $.each(fields, function(b, fieldName) {
                var td = $('<td />').appendTo(tr),
                    tdText;

                tdText = view.isNestedField(fieldName) ? view.getNestedValue(item, fieldName) : item[fieldName];

                td.html(tdText);

                fieldName = view.isNestedField(fieldName) ? fieldName.replace(view.separator, ".") : fieldName;
                td.data('bind', 'parsedData.lineItemRecords.' + a + '.' + fieldName);
            });
        },

        isNestedField: function(fieldName) {
            return fieldName.indexOf(this.separator) !== -1;
        },

        isValidNestedItem: function(fieldName, item) {
            var composedFields = fieldName.split(this.separator);

            return item.hasOwnProperty(composedFields[0]) && item[composedFields[0]].hasOwnProperty(composedFields[1]);
        },

        getNestedValue: function(item, fieldName) {
            var composedFields = fieldName.split(this.separator);

            return this.isValidNestedItem(fieldName, item) ? item[composedFields[0]][composedFields[1]] : "";
        },

        attachEventsToCells: function(container, model) {
            var view = this;

            container
                .undelegate('td', 'click')
                .undelegate('td:not(.no-bind)', 'click')
                .delegate('td:not(.no-bind)', 'click', function(e) {
                    var td = $(this),
                        value = model.get(td.data('bind'), value),
                        input = null,
                        width = td.width(),
                        height = td.height();

                    if (value instanceof Object) {
                        var taValue = [];

                        $.each(value, function(i, x) {
                            taValue.push(i + ':' + x);
                        });

                        input = $('<textarea class="input-control"></textarea>').val(taValue.join('\n'));
                        input.css({overflowY: 'hidden'});
                    } else {
                        input = $('<input type="text" class="input-control" />').val(value);
                    }

                    td.css({ padding: 0, width: width });

                    input.width(width);
                    input.css('minHeight', height);

                    td.data('original-value', value);

                    td.empty();
                    input.appendTo(td).focus();

                    e.stopPropagation();
                })
                .delegate('td > :input', 'click', function(e) {
                    e.stopPropagation();
                })
                .delegate('td > :input', 'keydown', function(e) {
                    if (e.which === 13) {
                        e.stopPropagation();
                    } else {
                        if (e.which === 27) {
                            $(this).trigger('blur', {
                                reset: true
                            });
                        }
                    }
                })
                .delegate('td > textarea', 'input', function() {
                    this.style.height = 'auto';
                    this.style.height = (this.scrollHeight) + 'px';
                })
                .delegate('td > :input', 'blur', function(e, opt) {
                    var tdText,
                        options = opt || {reset: false},
                        input = $(this),
                        td = input.parent(),
                        value = options.reset === true ? td.data('original-value') : input.val();

                    if (input.is('textarea')) {
                        value = input.val().split('\n').reduce(function(obj, line) {
                            var item = line.split(':'),
                                name = $.trim(item[0]),
                                value = $.trim(item[1]);

                            obj[name] = value;

                            return obj;
                        }, {});
                    }

                    td.css({ padding: '', width: '', height: '' });

                    if (value instanceof Object) {
                        var taValue = [];

                        $.each(value, function(i, x) {
                            taValue.push(i + ':' + x + '\n');
                        });

                        tdText = taValue.join('<br/>');
                    } else {
                        tdText = value;
                    }

                    input.remove();
                    td.html(tdText);

                    model.set(td.data('bind'), value);
                })
                .undelegate('tr > td > a.delete-line', 'click')
                .delegate('tr > td > a.delete-line', 'click', function(e) {
                    var lineIndex = $(this).parent().parent().index();
                    var target = $(e.target);

                    if (window.confirm(view.confirmMessage)) {
                        view.model.attributes.parsedData.lineItemRecords.splice(lineIndex, 1);
                        target.trigger('change');
                        view.renderLineItemsTableBody();
                    }

                    return false;
                });
        },

        getLineItemHeaders: function(columnDefinitions) {
            if (columnDefinitions !== "" && columnDefinitions !== null && columnDefinitions !== undefined) {
                return columnDefinitions.split(",");
            } else {
                return this.getNestedLineItemHeaders(this.lineItems);
            }
        },

        getNestedLineItemHeaders: function(lineItems) {
            var view = this;

            return Object.keys(lineItems.reduce(function(result, lineItem) {
                return Object.assign(result, view.processNestedHeader(lineItem));
            }, {}));
        },

        processNestedHeader: function(lineItemObj) {
            var flattened = {};

            for (var headerField in lineItemObj) {
                if (!lineItemObj.hasOwnProperty(headerField)) { continue; }

                if ((typeof lineItemObj[headerField]) === 'object') {
                    var innerNestedHeaders = this.processNestedHeader(lineItemObj[headerField]);

                    for (var header in innerNestedHeaders) {
                        if (!innerNestedHeaders.hasOwnProperty(header)) { continue; }

                        flattened[headerField + this.separator + header] = innerNestedHeaders[header];
                    }
                } else {
                    flattened[headerField] = lineItemObj[headerField];
                }
            }

            return flattened;
        },

        insertRow: function() {
            var newLineItem = {};
            var nestedFields = [];

            for (var i = 0; i < this.lineItemHeaders.length; i++) {
                if (this.isNestedField(this.lineItemHeaders[i])) {
                    nestedFields = this.lineItemHeaders[i].split(this.separator);

                    newLineItem[nestedFields[0]] = {};
                    newLineItem[nestedFields[0]][nestedFields[1]] = "";
                } else {
                    newLineItem[this.lineItemHeaders[i]] = "";
                }
            }

            this.model.attributes.parsedData.lineItemRecords.push(newLineItem);
            this.renderLineItemsTableBody();
        },

        getFieldTypeByName: function(fieldName) {
            var multilineArray = [
                'deliveryterms',
                'headercomments',
                'termsofpayment',
                'termsandconditions',
                'subject'
            ];

            return ($.inArray(fieldName.toLowerCase(), multilineArray) !== -1);

        }
    });

    return View;
});
