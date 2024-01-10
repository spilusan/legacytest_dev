define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'text!templates/reports/webreporter/tpl/allPoBody.html',
	'text!templates/reports/webreporter/tpl/allPoBodyForMms.html',
	'text!templates/reports/webreporter/tpl/allQotBody.html',
	'text!templates/reports/webreporter/tpl/allReqBody.html',
	'text!templates/reports/webreporter/tpl/allRfqBody.html',
	'text!templates/reports/webreporter/tpl/poSupplierBody.html',
	'text!templates/reports/webreporter/tpl/poVesselBody.html',
	'text!templates/reports/webreporter/tpl/txnSupplierBody.html',
	'text!templates/reports/webreporter/tpl/txnVesselBody.html',
	'text!templates/reports/webreporter/tpl/spbSumBody.html',
	'text!templates/reports/webreporter/tpl/spbSumPendBody.html',
	'text!templates/reports/webreporter/tpl/spbRfqBody.html',
	'text!templates/reports/webreporter/tpl/spbQotBody.html',
	'text!templates/reports/webreporter/tpl/spbQotPendBody.html',
	'text!templates/reports/webreporter/tpl/spbOrdBody.html',
	'text!templates/reports/webreporter/tpl/spbFullBody.html'
], function(
	$, 
	_, 
	Backbone,
	Hb,
	allPoBodyTpl,
	allPoBodyForMmsTpl,
	allQotBodyTpl,
	allReqBodyTpl,
	allRfqBodyTpl,
	poSupplierBodyTpl,
	poVesselBodyTpl,
	txnSupplierBodyTpl,
	txnVesselBodyTpl,
	spbSumBodyTpl,
	spbSumPendBodyTpl,
	spbRfqBodyTpl,
	spbQotBodyTpl,
	spbQotPendBodyTpl,
	spbOrdBodyTpl,
	spbFullBodyTpl
){
	var tableRowView = Backbone.View.extend({
		tagName: 'tr',
		allPoBodyTemplate: Handlebars.compile(allPoBodyTpl),
		allPoBodyForMmsTemplate: Handlebars.compile(allPoBodyForMmsTpl),
		allQotBodyTemplate: Handlebars.compile(allQotBodyTpl),
		allReqBodyTemplate: Handlebars.compile(allReqBodyTpl),
		allRfqBodyTemplate: Handlebars.compile(allRfqBodyTpl),
		poSupplierBodyTemplate: Handlebars.compile(poSupplierBodyTpl),
		poVesselBodyTemplate: Handlebars.compile(poVesselBodyTpl),
		txnSupplierBodyTemplate: Handlebars.compile(txnSupplierBodyTpl),
		txnVesselBodyTemplate: Handlebars.compile(txnVesselBodyTpl),
		spbSumBodyTemplate: Handlebars.compile(spbSumBodyTpl),
		spbSumPendBodyTemplate: Handlebars.compile(spbSumPendBodyTpl),
		spbRfqBodyTemplate: Handlebars.compile(spbRfqBodyTpl),
		spbQotBodyTemplate: Handlebars.compile(spbQotBodyTpl),
		spbQotPendBodyTemplate: Handlebars.compile(spbQotPendBodyTpl),
		spbOrdBodyTemplate: Handlebars.compile(spbOrdBodyTpl),
		spbFullBodyTemplate: Handlebars.compile(spbFullBodyTpl),

		rptData: require('reports/data'),

		events: {
			'click a.showAllPoSup'     : 'showAllPoSup',
			'click a.showAllPoVes'     : 'showAllPoVes',
			'click a.showAllRfqTxnSup' : 'showRfqTxnSup',
			'click a.showDecRfqTxnSup' : 'showRfqTxnSup',
			'click a.showAllQotTxnSup' : 'showAllQotTxnSup',
			'click a.showTxnPo'        : 'showAllPoSup',
			'click a.showAllRfqTxnVes' : 'showRfqTxnVes',
			'click a.showDecRfqTxnVes' : 'showRfqTxnVes',
			'click a.showAllQotTxnVes' : 'showAllQotTxnVes',
			'click a.showTxnPoVes'     : 'showAllPoVes',
			'click a.showAllReqTxnVes' : 'showAllReqTxnVes'
		},

		initialize: function(){
			_.bindAll(this, 'render');
			this.model.view = this;
		},

		setTemplate: function(data){
			switch(this.parent.formData.rptCode) {
			    case "GET-ALL-ORD":
			        var html = this.allPoBodyTemplate(data);
			        return html;
			        break;
			    case "GET-ALL-ORD-MMS":
			        var html = this.allPoBodyForMmsTemplate(data);
			        return html;
			        break;
		        case "GET-ALL-RFQ":
			        var html = this.allRfqBodyTemplate(data);
			        return html;
			        break;
			    case "GET-ORD-SUPPLIERS":
			    	var html = this.poSupplierBodyTemplate(data);
			    	return html;
			    	break;
			    case "GET-ORD-VESSELS":
			    	var html = this.poVesselBodyTemplate(data);
			    	return html;
			    	break;
			    case "GET-SPB-ANALYSIS":
			    	var html = this.setSpbTemplate(data);
			    	return html;
			    	break;
			    case "GET-TXN-SUPPLIERS":
			    	var html = this.txnSupplierBodyTemplate(data);
			    	return html;
			    	break;
			    case "GET-TXN-VESSELS":
			    	var html = this.txnVesselBodyTemplate(data);
			    	return html;
			    	break;
			    case "GET-ALL-QOT":
			        var html = this.allQotBodyTemplate(data);
			        return html;
			        break;
			    case "GET-ALL-REQ":
			        var html = this.allReqBodyTemplate(data);
			        return html;
			        break;
			}
		},

		setSpbTemplate: function(data){
			switch(this.parent.spbReport) {
				case "summary":
					return this.spbSumBodyTemplate(data);
					break;
				case "sumPendQot":
					return this.spbSumPendBodyTemplate(data);
					break;
				case "rfqKpi":
					return this.spbRfqBodyTemplate(data);
					break;
				case "qotKpi":
					return this.spbQotBodyTemplate(data);
					break;
				case "qotKpiPendQot":
					return this.spbQotPendBodyTemplate(data);
					break;
				case "ordKpi":
					return this.spbOrdBodyTemplate(data);
					break;
				case "full":
					return this.spbFullBodyTemplate(data);
					break;
			}
		},

	    render: function() {			
			var data = this.model.attributes;
			data.rptData = this.rptData;
			data.rptData.branchCode = this.parent.formData.bybTnid;
			data.rptData.iscnsldt = this.parent.formData.iscnsldt;

			if(data.ordsts){
				switch(data.ordsts) {
					case "ACC":
						data.ordsts = "Accepted";
						break;
					case "ACK":
						data.ordsts = "Acknowledged";
					case "AWA":
						data.ordsts = "Awaiting";
						break;
					case "NEW":
						data.ordsts = "Awaiting";
						break;
					case "CON":
						data.ordsts = "Confirmed";
						break;
					case "DEC":
						data.ordsts = "Declined";
						break;
					case "OPN":
						data.ordsts = "Open";
						break;
				}
			}

			var html = this.setTemplate(data);
			
			$(this.el).html(html);

			return this;
	    },

	    showAllPoSup: function(e) {
	    	e.preventDefault();

	    	this.setPrevData();

	    	if($(e.target).hasClass('showTxnPo')){
	    		this.parent.returnTo = 'txnSup';
	    	}
	    	else {
	    		this.parent.returnTo = 'posup';	
	    	}
	    	
	    	$('select#reportType option').removeAttr('selected');
	    	$('select#reportType option[value="GET-ALL-ORD:ORDSUBDT"]').attr('selected', 'selected');

	    	$('select#supplier option').removeAttr('selected');
	    	$('select#supplier option[value="' + this.model.attributes.spbtnid + '"]').attr('selected', 'selected');
	    	$('select#supplier').attr('disabled', 'disabled');
	    	$('select#vessel').attr('disabled', 'disabled');
	    	$('select#contact').attr('disabled', 'disabled');

	    	$.uniform.update();
	    	
    		this.parent.prevSpbTnid = this.parent.formData.spbTnid;
    		this.parent.prevSpbName = this.parent.formData.spbName;

	    	this.parent.formData.rptType = "SUB";
	    	this.parent.formData.rptCode = "GET-ALL-ORD";
	    	this.parent.formData.rptOrd = "ORDSUBDT";
	    	this.parent.formData.spbTnid = this.model.attributes.spbtnid;
	    	this.parent.formData.spbName = this.model.attributes.spbname;

	    	if($(e.target).hasClass('showTxnPo')){
	    		$('h1 .return').text('Return to Transactions by Supplier');
	    	}
	    	else {
	    		$('h1 .return').text('Return to POs by Supplier');
	    	}

	    	if($(e.target).hasClass('acc')){
	    		this.parent.formData.ordIsAcc = 1;
	    	}

	    	if($(e.target).hasClass('dec')){
	    		this.parent.formData.ordIsDec = 1;
	    	}

	    	if($(e.target).hasClass('poc')){
	    		this.parent.formData.ordIsPoc = 1;
	    	}

	    	this.parent.getData(1);
	    },

	    showAllPoVes: function(e) {
	    	e.preventDefault();

	    	this.setPrevData();
	    	
	    	if($(e.target).hasClass('showTxnPoVes')){
	    		this.parent.returnTo = 'txnVes';
	    	}
	    	else {
	    		this.parent.returnTo = 'poves';	
	    	}

	    	$('select#reportType option').removeAttr('selected');
	    	$('select#reportType option[value="GET-ALL-ORD:ORDSUBDT"]').attr('selected', 'selected');

	    	$('select#vessel option').removeAttr('selected');
	    	$('select#vessel option[value="' + this.model.attributes.vesid + '"]').attr('selected', 'selected');
	    	$('select#supplier').attr('disabled', 'disabled');
	    	$('select#vessel').attr('disabled', 'disabled');
	    	$('select#contact').attr('disabled', 'disabled');

	    	$.uniform.update();
	    	
			this.parent.prevVesselId = this.parent.formData.vesselId;
    		this.parent.prevVesselName = this.parent.formData.vesselName;

	    	this.parent.formData.rptType = "SUB";
	    	this.parent.formData.rptCode = "GET-ALL-ORD";
	    	this.parent.formData.rptOrd = "ORDSUBDT";
	    	this.parent.formData.vesselId = this.model.attributes.vesid;
	    	this.parent.formData.vesselName = this.model.attributes.vesname;

	    	if($(e.target).hasClass('showTxnPo')){
	    		$('h1 .return').text('Return to Transactions by Vessel');
	    	}
	    	else {
	    		$('h1 .return').text('Return to POs by Vessel');
	    	}

	    	if($(e.target).hasClass('acc')){
	    		this.parent.formData.ordIsAcc = 1;
	    	}

	    	if($(e.target).hasClass('dec')){
	    		this.parent.formData.ordIsDec = 1;
	    	}

	    	if($(e.target).hasClass('poc')){
	    		this.parent.formData.ordIsPoc = 1;
	    	}

	    	this.parent.getData(1);
	    },

	    showRfqTxnSup: function(e) {
	    	e.preventDefault();

			this.setPrevData();

	    	this.parent.returnTo = 'txnSup';
	    	$('select#reportType option').removeAttr('selected');
	    	$('select#reportType option[value="GET-ALL-RFQ:RFQSUBDT"]').attr('selected', 'selected');

	    	$('select#supplier option').removeAttr('selected');
	    	$('select#supplier option[value="' + this.model.attributes.spbtnid + '"]').attr('selected', 'selected');
	    	$('select#supplier').attr('disabled', 'disabled');
	    	$('select#vessel').attr('disabled', 'disabled');
	    	$('select#contact').attr('disabled', 'disabled');

	    	$.uniform.update();
	    	
			this.parent.prevSpbTnid = this.parent.formData.spbTnid;
    		this.parent.prevSpbName = this.parent.formData.spbName;

	    	this.parent.formData.rptType = "SUB";
	    	this.parent.formData.rptCode = "GET-ALL-RFQ";
	    	this.parent.formData.rptOrd = "RFQSUBDT";
	    	this.parent.formData.spbTnid = this.model.attributes.spbtnid;
	    	this.parent.formData.spbName = this.model.attributes.spbname;
	    	
	    	if($(e.target).hasClass('showDecRfqTxnSup')){
	    		this.parent.formData.rfqIsDec = 1;
	    	}

	    	$('h1 .return').text('Return to Transactions by Supplier');

	    	this.parent.getData(1);
	    },

	    showRfqTxnVes: function(e) {
	    	e.preventDefault();

	    	this.setPrevData();

	    	this.parent.returnTo = 'txnVes';
	    	$('select#reportType option').removeAttr('selected');
	    	$('select#reportType option[value="GET-ALL-RFQ:RFQSUBDT"]').attr('selected', 'selected');

	    	$('select#supplier option').removeAttr('selected');
	    	$('select#supplier option[value="' + this.model.attributes.spbtnid + '"]').attr('selected', 'selected');
	    	$('select#supplier').attr('disabled', 'disabled');
	    	$('select#vessel').attr('disabled', 'disabled');
	    	$('select#contact').attr('disabled', 'disabled');

	    	$.uniform.update();
	    	
			this.parent.prevVesselId = this.parent.formData.vesselId;
    		this.parent.prevVesselName = this.parent.formData.vesselName;

	    	this.parent.formData.rptType = "SUB";
	    	this.parent.formData.rptCode = "GET-ALL-RFQ";
	    	this.parent.formData.rptOrd = "RFQSUBDT";
	    	this.parent.formData.vesselId = this.model.attributes.vesid;
	    	this.parent.formData.vesselName = this.model.attributes.vesname;
	    	
	    	if($(e.target).hasClass('showDecRfqTxnVes')){
	    		this.parent.formData.rfqIsDec = 1;
	    	}

	    	$('h1 .return').text('Return to Transactions by Vessel');

	    	this.parent.getData(1);
	    },

	    showAllQotTxnSup: function(e) {
	    	e.preventDefault();

	    	this.setPrevData();

	    	this.parent.returnTo = 'txnSup';
	    	$('select#reportType option').removeAttr('selected');
	    	
	    	$('select#reportType').append('<option value="GET-ALL-QOT:QOTSUBDT">All Quotes</option>');
	    	
	    	$('select#reportType option[value="GET-ALL-QOT:QOTSUBDT"]').attr('selected', 'selected');

	    	$('select#supplier option').removeAttr('selected');
	    	$('select#supplier option[value="' + this.model.attributes.spbtnid + '"]').attr('selected', 'selected');
	    	$('select#supplier').attr('disabled', 'disabled');
	    	$('select#vessel').attr('disabled', 'disabled');
	    	$('select#contact').attr('disabled', 'disabled');

	    	$.uniform.update();
	    	
    		this.parent.prevSpbTnid = this.parent.formData.spbTnid;
    		this.parent.prevSpbName = this.parent.formData.spbName;

	    	this.parent.formData.rptType = "SUB";
	    	this.parent.formData.rptCode = "GET-ALL-QOT";
	    	this.parent.formData.rptOrd = "QOTSUBDT";
	    	this.parent.formData.spbTnid = this.model.attributes.spbtnid;
	    	this.parent.formData.spbName = this.model.attributes.spbname;

	    	$('h1 .return').text('Return to Transactions by Supplier');

	    	this.parent.getData(1);
	    },

	    showAllQotTxnVes: function(e) {
	    	e.preventDefault();

	    	this.setPrevData();

	    	this.parent.returnTo = 'txnVes';
	    	$('select#reportType option').removeAttr('selected');
	    	
	    	$('select#reportType').append('<option value="GET-ALL-QOT:QOTSUBDT">All QOTs</option>');
	    	
	    	$('select#reportType option[value="GET-ALL-QOT:QOTSUBDT"]').attr('selected', 'selected');

	    	$('select#supplier option').removeAttr('selected');
	    	$('select#vessel option[value="' + this.model.attributes.vesid + '"]').attr('selected', 'selected');
	    	$('select#supplier').attr('disabled', 'disabled');
	    	$('select#vessel').attr('disabled', 'disabled');
	    	$('select#contact').attr('disabled', 'disabled');

	    	$.uniform.update();
	    	
    		this.parent.prevVesselId = this.parent.formData.vesselId;
    		this.parent.prevVesselName = this.parent.formData.vesselName;

	    	this.parent.formData.rptType = "SUB";
	    	this.parent.formData.rptCode = "GET-ALL-QOT";
	    	this.parent.formData.rptOrd = "QOTSUBDT";
	    	this.parent.formData.vesselId = this.model.attributes.vesid;
	    	this.parent.formData.vesselName = this.model.attributes.vesname;

	    	$('h1 .return').text('Return to Transactions by Supplier');

	    	this.parent.getData(1);
	    },

	    showAllReqTxnVes: function(e) {
	    	e.preventDefault();

	    	this.setPrevData();

	    	this.parent.returnTo = 'txnVes';
	    	$('select#reportType option').removeAttr('selected');
	    	
	    	$('select#reportType').append('<option value="GET-ALL-REQ:REQSUBDT">All REQs</option>');
	    	
	    	$('select#reportType option[value="GET-ALL-REQ:REQSUBDT"]').attr('selected', 'selected');

	    	$('select#supplier option').removeAttr('selected');
	    	$('select#vessel option[value="' + this.model.attributes.vesid + '"]').attr('selected', 'selected');
	    	$('select#supplier').attr('disabled', 'disabled');
	    	$('select#vessel').attr('disabled', 'disabled');
	    	$('select#contact').attr('disabled', 'disabled');

	    	$.uniform.update();
	    	
    		this.parent.prevVesselId = this.parent.formData.vesselId;
    		this.parent.prevVesselName = this.parent.formData.vesselName;

	    	this.parent.formData.rptType = "SUB";
	    	this.parent.formData.rptCode = "GET-ALL-REQ";
	    	this.parent.formData.rptOrd = "REQSUBDT";
	    	this.parent.formData.vesselId = this.model.attributes.vesid;
	    	this.parent.formData.vesselName = this.model.attributes.vesname;

	    	$('h1 .return').text('Return to Transactions by Vessel');

	    	this.parent.getData(1);
	    },

	    setPrevData: function(){
	    	this.parent.prevPage = this.parent.page;
    		this.parent.prevRows = this.parent.rowCount;
    		this.parent.prevCurr = this.parent.formData.currency;
    		this.parent.prevRowLimit = this.parent.formData.rows;
    		this.parent.prevNumFormat = this.parent.formData.numFormat;
    		this.parent.prevFromDate = $('input[name="fromDate"]').val();
    		this.parent.prevToDate = $('input[name="toDate"]').val();
    		this.parent.prevPostFromDate = this.parent.formData.fromDate;
    		this.parent.prevPostToDate = this.parent.formData.toDate;
    		this.parent.prevDateRange = this.parent.formData.dateRange;
	    	this.parent.formData.isDrill = 1;
	    	this.parent.page = 1;
	    }
	});

	return tableRowView;
});