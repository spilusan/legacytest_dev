Number.prototype.formatMoney = function(c, d, t){
var n = this,
    c = isNaN(c = Math.abs(c)) ? 2 : c,
    d = (d == undefined ? "." : d),
    t = (t == undefined ? "," : t),
    s = n < 0 ? "-" : "",
    i = parseInt(n = Math.abs(+n || 0).toFixed(c)) + "",
    j = (j = i.length) > 3 ? j % 3 : 0;
   return s + (j ? i.substr(0, j) + t : "") + i.substr(j).replace(/(\d{3})(?=\d)/g, "$1" + t) + (c ? d + Math.abs(n - i).toFixed(c).slice(2) : "");
 };

define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'backbone/shared/hbh/general',
	'libs/jquery.uniform',
	"libs/jquery.dataTables.min",
	"libs/dataTables.tableTools",
    'libs/jquery.autocomplete',
	'text!templates/shipmate-manage-user/tpl/test.html',
	'text!templates/shipmate-manage-user/tpl/supplier.html',
	'text!templates/shipmate-manage-user/tpl/buyer-branch.html',
	'text!templates/shipmate-manage-user/tpl/user.html',
	'text!templates/shipmate-manage-user/tpl/user/company.html',
	'text!templates/shipmate-manage-user/tpl/user/activity.html',
	'text!templates/shipmate-manage-user/tpl/company/user.html',
    'text!templates/shipmate-manage-user/tpl/consortia.html',
], function(
	$,
	_,
	Backbone,
	Hb,
	Hbh,
	Uniform,
	dt,
	dtt,
	autoComplete,
	testTpl,
	supplierTpl,
	buyerBranchTpl,
	userTpl,
	userCompanyTpl,
	userActivityTpl,
	companyUserTpl,
	consortiaTpl
){
	var mainView = Backbone.View.extend({

		el: $('body'),

		/**
		 * Handlebars template
		 */
		testTemplate: Handlebars.compile(testTpl),
		supplierTemplate: Handlebars.compile(supplierTpl),
		buyerBranchTemplate: Handlebars.compile(buyerBranchTpl),
		userTemplate: Handlebars.compile(userTpl),
		userCompanyTemplate: Handlebars.compile(userCompanyTpl),
		userActivityTemplate: Handlebars.compile(userActivityTpl),
		companyUserTemplate: Handlebars.compile(companyUserTpl),
        consortiaTemplate: Handlebars.compile(consortiaTpl),

		/**
		 * Handling back buttons
		 */
		actions: [],

		/**
		 * Storing primary key of what's being displayed on the right
		 */
		activeId: null,

		/**
		 * Storing data type of what's being displayed on the right
		 */
		activeType: null,

		/**
		 * AutoComplete for SPB, BYB, BYO
		 */
		autoCompleteUrl: null,
		autoCompleteCallBack: null,
		transformationCallback: null,
		accountIsDeleted: 'N',

		/**
		 * AJAX XHR
		 */
		xhr: null,

		xhrOnError: function(request, textStatus, errorThrown) {
			$("#xhrProgress").html('Problem completing task...').fadeIn(400);
	    	response = eval('(' + request.responseText + ')');
	    	that.writeToRightPanel("Error: " + request.status + ": " + response.error);
	    },

	    /**
	     * Storing serialized stack call in executable format
	     */
		wrapFunction: function(fn, context, params){
		    return function() {
		        fn.apply(context, params);
		    };
		},

		/**
		 * To handle history on the plugin
		 */
		addAction: function(fn){
			this.backButtonPreviouslyClicked = false;
			this.actions.push(fn);

			$("#goBackBtn").show();
		},

		goBack: function(){

			if( this.backButtonPreviouslyClicked == false ){
				this.actions.pop();
			}

			this.backButtonPreviouslyClicked = true;

			if (this.actions.length === 0) {
				return;
			}

			this.actions.pop().call();

		},

        refresh: function(){
            var a = this.actions.pop();
            a.call();
        },

		events: {
			'click input[name="search"]' : 'search', 
			'submit form[name="searchForm"]' : 'search', 
			'click input[name="goBackBtn"]' : 'goBack',
			'click input[name="sprStatusCb"]' : 'sprStatusChange',
			'change select[name="membershipLevel"]' : 'membershipLevelChange'
		},

		initialize: function () {
			//$('.uniform').uniform();
            var that = this;

            $(document).ajaxError(function (e, xhr, options) {
                if (xhr.readyState == 4  && xhr.status !== 200) {
                	if (xhr.responseText) {
                    	var errorObj = $.parseJSON(xhr.responseText);
                    	if (errorObj.error) {
                    		alert("Error: " + errorObj.error);
                            that.refresh();
                    	}
                    }
                }
            });
		},

		/** ***********************************************************************
		 *  SEARCH MODULE
		 ** ***********************************************************************/
		search: function() {
			var that = this;
			var searchType = $('select[name="searchType"]').val();

			this.addAction(this.wrapFunction(this.search, this));

			if( ( searchType == 'all' || searchType == 'usr' || searchType == 'spb' || searchType == 'byo' || searchType == 'byb' ) && $('input[name="searchQuery"]').val().length < 3 ){
				that.writeToLeftPanel('Please enter the first 3 letters');
				$('input[name="search"]').focus();
				return;
			}

			that.writeToLeftPanel('Searching...');
			that.asyncRequest({
				url: '/shipmate/manage-user?a=search',
				data: {a: 'search', q: $('input[name="searchQuery"]').val(), t: $('select[name="searchType"]').val()},
				type: 'GET',
				cache: false,
			    error: that.xhrOnError,
				success: function( response ){
					that.initSearchResult(response);
					if( response.length == 0 ){
						that.writeToLeftPanel('Nothing found.');
						that.writeToRightPanel('');
					}else{
						that.writeToRightPanel(response.length.formatMoney(0, '.', ',') + ' rows found. Click to see details.');
					}
				}
			});
		},

		initSearchResult: function(data){
			var html = '';
			var that = this;
			data.forEach(function(row){
				html += that.renderSearchResultRow(row);
			});
			$('.pane .left .content').html(html);
			$('.pane .left .content .r').bind('click', function(e){
				var id = $(this).attr('dataId');
				var type = $(this).attr('dataType');

				$('.pane .left .content .r').removeClass('active');
				$(this).addClass('active');

				that.renderSearchResult(type, id);
			});
		},

		renderSearchResult: function(type, id){
			var that = this;
			if ( type == 'user'){
				that.renderUserDetail(id);
			} else if(type == 'supplier') {
				that.renderSupplierDetail(id);
			} else if(type == 'buyer-branch') {
				that.renderBuyerBranchDetail(id);
			} else if(type == 'buyer-org') {
				that.renderBuyerOrganisationDetail(id);
			} else if(type == 'consortia') {
				that.renderConsortiaDetail(id);
            } else {
				console.log(type);
			}
			$('.right .content').undelegate('.search-link', 'click').delegate('.search-link', 'click', function(e){
				var id = $(this).attr('dataId');
				var type = $(this).attr('dataType');
				$('.pane .left .content .r').removeClass('active');
				that.renderSearchResult(type, id);
			});
		},
		
		renderSearchResultRow: function(data){
			if( data.TITLE.length <= 2 ) data.TITLE = 'Unspecified';

			var html = '';
			html += '<div class="r" dataId="' + data.ID + '" dataType="' + data.ROW_TYPE + '">';
				html += '<div class="n">';
					if (data.DESCRIPTION && data.DESCRIPTION.indexOf("COMPANY") > -1) {
						html = html + '<span style="color:red">' + this.ucwords(data.TITLE) + '</span>';
					} else {
						html += this.ucwords(data.TITLE);
					}
					
				html += '</div>';
				html += '<div class="c">';
					html += data.ROW_TYPE.toUpperCase() +  ((data.DESCRIPTION!=null)?' - ' + data.DESCRIPTION : '');
				html += '</div>';
			html += '</div>';

			return html;
		},

		/** ***********************************************************************
		 *  BYO
		 ** ***********************************************************************/
		renderBuyerOrganisationDetail: function(companyId){

			this.addAction(this.wrapFunction(this.renderBuyerOrganisationDetail, this, [companyId]));

			var that = this;
			that.asyncRequest({
				url: '/shipmate/manage-user',
				data: {a: 'byo', q: companyId},
				type: 'GET',
				cache: false,
			    error: that.xhrOnError,
				success: function( response ){
					that.renderBuyerOrganisationDetailRow(response);
				}
			});
		},

		renderBuyerOrganisationDetailRow: function(data){
			
			var html = '';
			var that = this;
			
			var kpiSprChekcedAttr = (parseInt(data.byoAccessKpiSp) === 1) ? 'checked' : '';
			var kpiSprOverrided = (parseInt(data.sprAllowOverride) === 1) ? '&nbsp;(global override is activated.)' : '';
			
			var membershipOptions = {
					full: {
						id: 1,
						name: 'FULL'
					},
					basic: {
						id: 0,
						name: 'BASIC'
					}
				}
			
			html += '<div class="detail">';

				html += '<h2>' + data.byoName + '</h2>';

				html += '<h3>Buyer Organisation</h3>';
				html += '<div class="group">';

					html += '<div class="group-row">';
						html += '<div class="caption">Organization ID</div>';
						html += '<div class="data-value">'  + data.byoOrgCode + '</div>';
					html += '</div>';

					html += '<div class="group-row">';
						html += '<div class="caption">Parent Org</div>';
						html += '<div class="data-value">';						
						if (data.normalisationOfCompanyId) {
							html += '<span class="search-link" dataId=" ' + data.normalisationOfCompanyId + '" dataType="buyer-org">'  + data.normalisationOfCompanyId + '</span>';	
						} else {
							html += '<i>null</i>';
						}						
						html += '</div>';
					html += '</div>';
					
					html += '<div class="group-row">';
						html += '<div class="caption">Child Orgs</div>';
						html += '<div class="data-value">';
						if (data.normalisingCompanyIds.length) {
							var companies = [];
							data.normalisingCompanyIds.forEach(function(id) {
								companies.push('<span class="search-link" dataId=" ' + id + '" dataType="buyer-org">' + id + '</span>');
							});
							html += companies.join(', ');
						} else {
							html += '<i>null</i>';
						}					
						html += '</div>';
					html += '</div>';
					
					html += '<div class="group-row">';
						html += '<div class="caption">Name</div>';
						html += '<div class="data-value container-interactive-input">';
							html += '<input size=50 class="interactive-input" dataId="' + data.byoOrgCode + '" value="' + data.byoName + '" disabled="disabled">';
						html += '</div>';
					html += '</div>';

					html += '<div class="group-row">';
						html += '<div class="caption">Joinable</div>';
						html += '<div class="data-value">'  + data.pcoIsJoinRequestable + '</div>';
					html += '</div>';

					html += '<div class="group-row">';
						html += '<div class="caption">Opt-out from reviews</div>';
						html += '<div class="data-value">'  + data.pcoReviewsOptout + '</div>';
					html += '</div>';

					html += '<div class="group-row">';
						html += '<div class="caption">Created by</div>';
						html += '<div class="data-value">'  + data.byoCreatedBy + '</div>';
					html += '</div>';

					html += '<div class="group-row">';
						html += '<div class="caption">Date created</div>';
						html += '<div class="data-value">'  + data.byoCreatedDate + '</div>';
					html += '</div>';

					/* New fields */

					html += '<div class="group-row">';
						html += '<div class="caption">Anoymised name</div>';
						html += '<div class="data-value">'  + data.pcoAnonymisedName + '</div>';
					html += '</div>';

					html += '<div class="group-row">';
						html += '<div class="caption">Anonymised location</div>';
						html += '<div class="data-value">'  + data.pcoAnonymisedLocation + '</div>';
					html += '</div>';

					html += '<div class="group-row">';
						html += '<div class="caption">Auto rev solicit</div>';
						html += '<div class="data-value">'  + data.pcoAutoRevSolicit + '</div>';
					html += '</div>';

					html += '<div class="group-row">';
						html += '<div class="caption">Disable rev submission</div>';
						html += '<div class="data-value">'  + data.pcoDisableRevSubmission + '</div>';
					html += '</div>';

					html += '<div class="group-row">';
						html += '<div class="caption">Transaction history search</div>';
						html += '<div class="data-value">'  + data.byoIsTransHistorySearch + '</div>';
					html += '</div>';
					
					html += '<div class="group-row">';
						html += '<div class="caption">Access KPI/SP reports</div>';
						html += '<div class="data-value"><input type="checkbox" name="sprStatusCb" style="width: auto;" data-id="' +  data.byoOrgCode + '"' + kpiSprChekcedAttr + '>' + kpiSprOverrided + '</div>';
					html += '</div>';
					
					html += '<div class="group-row">';
						html += '<div class="caption">Membership level</div>';
						html += '<div>';
						if (data.normalisationOfCompanyId) {
							var membersipLevelId = (data.parentBuyer.pcoMembershipLevel === null) ? 1 : parseInt(data.parentBuyer.pcoMembershipLevel);
							for (var key in membershipOptions) {
								if (membersipLevelId === parseInt(membershipOptions[key].id)) {
									html += '<div class="data-value">' + membershipOptions[key].name + '</div>';
								}
							}
						} else {
							html += '<select name="membershipLevel" data-id="' +  data.byoOrgCode + '">';
							for (var key in membershipOptions) {
								if (parseInt(data.pcoMembershipLevel) === parseInt(membershipOptions[key].id)) {
									html += '<option value="' + membershipOptions[key].id + '" selected>' + membershipOptions[key].name + '</option>';
								} else {
									html += '<option value="' + membershipOptions[key].id + '">' + membershipOptions[key].name + '</option>';
								}
							}
							html += '</select>';
						}
						html += '</div>';
					html += '</div>';
				
					/* End of new fields */
	
					if (data.normalisationOfCompanyId === null) {
						html += '<div class="group-row">';
							html += '<a id="orgStrucBtn" data-id="'+  data.byoOrgCode +'" class="button small green" href="/shipmate/manage-user/byo-drilldown?byo='+  data.byoOrgCode +'" target="_blank">Display Organisation Structure </a>';
							html += '<i class="fa fa-spinner fa-spin"></i>';
							html += '<div id="orgStrucContainer"></div>';
						html += '</div>';
					}

					html += '<h2>Connected users</h2>';
					html += '<div class="company-membership"></div>';

					html += '<h2>Connected Buyer Branch</h2>';
					html += '<div class="connected-buyer-branch"></div>';

				html += '</div>';
			html += '</div>';

			$('.right .content').html(html);

			$('#orgStrucBtn').unbind('click').bind('click', function(e){
				e.preventDefault();
				that.getByoStructure($(this).data('id'));
			});

			that.initInteractiveInput();
			
			that.renderCompanyUser(data.byoOrgCode, 'byo', function(){
				that.renderConnectedBuyerBranch(data.byoOrgCode);
			});
		},

		initInteractiveInput: function(){
			var that = this;
			$('.right .content').undelegate('.container-interactive-input', 'dblclick').delegate('.container-interactive-input', 'dblclick', function(e){
				if ($(this).children().attr('disabled') != undefined) {
					$(this).children().removeAttr('disabled');
					$(this).children().focus();
				} else {
					$(this).children().attr('disabled', 'disabled');
				}
			    if ($(this).children() && $(this).children().get(0) && $(this).children().get(0).selectionStart != undefined) {
			    	$(this).children().get(0).selectionStart = $(this).children().get(0).selectionEnd = 0;
			    }
			});
			$('.right .content').undelegate('.interactive-input', 'blur').delegate('.interactive-input', 'blur', function(e){
				$(this).attr('disabled', 'diabled');
				that.changeOrganisationName($(this).attr('dataId'), $(this).val());
			});
			$('.right .content').undelegate('.interactive-input', 'keyup').delegate('.interactive-input', 'keyup', function(e){
			    if (e.keyCode == 13) {
			    	this.blur();
			    }
			});
		},

		changeOrganisationName: function(id, name) {
			$.ajax({
				type: 'POST',
				url: '/shipmate/change-organisation-name', 
				data: {id: id, name: name},
				beforeSend: function(){
					$("#xhrProgress").html('submitting...').fadeIn(500);
				}
			})
			.fail(function(response){ 
				$("#xhrProgress").html('http failure. see console errors').fadeOut(3000);
				console.log(response);
			})
			.done(function(response){
				if (response && response.status==='success') {
					$("#xhrProgress").html('successfully updated').fadeOut(1000);
				} else {
					$("#xhrProgress").html('http failure. see console errors').fadeOut(3000);
					console.log(response.description);
				} 
			});
		},		
		
		renderConnectedBuyerBranch: function(companyId){
			var that = this;
			that.asyncRequest({
				url: '/shipmate/manage-user',
				data: {a: 'byo-byb', q: companyId},
				type: 'GET',
				cache: false,
			    error: that.xhrOnError,
				success: function( response ){
					that.renderConnectedBuyerBranchRow(response);
				}
			});
		},

		renderConnectedBuyerBranchRow: function(data) {
			var that = this;
			var html = '';
			var count = 0;

			html += '<table>';
				html += '<thead><tr>';
				html += '<td style="width:30px"></td>';
				html += '<td style="width:30px">TNID</td>';
				html += '<td style="width:230px">Name</td>';
			html += '</tr></thead>';
			html += '<tbody>';

			data.forEach(function(row){
				count++;
				html += '<tr>';
					html += '<td>' + count + '.</td>';
					html += '<td>' + '<a class="linkToByb" dataId="' +  row.BYB_BRANCH_CODE +'">' + row.BYB_BRANCH_CODE + '</a></td>';
					html += '<td>' + row.BYB_NAME + '</td>';
				html += '</tr>';
			});

			if (count === 0) {
				html += '<tr><td colspan="4">No connected user</td></tr>';
			}

			html += '</tbody>';
			html += '</table>';

			$('.connected-buyer-branch').html(html);
			$('.linkToByb').unbind('click').bind('click', function(){
				that.renderBuyerBranchDetail($(this).attr('dataId'));
			});

		},

		/** ***********************************************************************
		 *  BYB
		 ** ***********************************************************************/
		renderBuyerBranchDetail: function(companyId){
			this.addAction(this.wrapFunction(this.renderBuyerBranchDetail, this, [companyId]));
			var that = this;

			that.asyncRequest({
				url: '/shipmate/manage-user',
				data: {a: 'byb', q: companyId},
				type: 'GET',
				cache: false,
			    error: that.xhrOnError,
				success: function( response ){
					that.renderBuyerBranchDetailRow(response);
				}
			});
		},

		renderBuyerBranchDetailRow: function(data){
			var html = this.buyerBranchTemplate(data);
			$('.right .content').html(html);

			$("#saveDataBtn").unbind('click').bind('click', function(){
				this.saveBuyerDetail();
			});

			this.renderCompanyUser(data.bybBranchCode, 'byb');
		},


		saveBuyerDetail: function(){
			alert('please implement saving of byb');
		},

		/** ***********************************************************************
		 *  SPB
		 ** ***********************************************************************/

		renderSupplierDetail: function(companyId){
			var that = this;

			this.addAction(this.wrapFunction(this.renderSupplierDetail, this, [companyId]));

			that.asyncRequest({
				url: '/shipmate/manage-user',
				data: {a: 'spb', q: companyId},
				type: 'GET',
				cache: false,
			    error: that.xhrOnError,
				success: function( response ){
					that.accountIsDeleted = response.accountIsDeleted;
					that.renderSupplierDetailRow(response);
				}
			});

		},

		renderSupplierDetailRow: function(data){
			var html = this.supplierTemplate(data);
			$('.right .content').html(html);

			$("#saveDataBtn").unbind('click').bind('click', this.saveSupplierDetail);

			this.renderCompanyUser(data.tnid, 'spb');
		},

		saveSupplierDetail: function(){
			alert('please impelement saving of spb');
		},


        renderConsortiaDetailRow: function(data){
            var html = this.consortiaTemplate(data);
            $('.right .content').html(html);

            $("#saveDataBtn").unbind('click').bind('click', this.saveSupplierDetail);

            this.renderCompanyUser(data.internalRefNo, 'con');
        },

		/** ***********************************************************************
		 *  USER DETAIL
		 ** ***********************************************************************/

		renderUserDetail: function(userId){
			var that = this;
			this.addAction(this.wrapFunction(this.renderUserDetail, this, [userId]));
			$('.right .content').html('');
			that.asyncRequest({
				url: '/shipmate/manage-user',
				data: {a: 'user', q: userId},
				type: 'GET',
				cache: false,
			    error: that.xhrOnError,
				success: function( response ){
					that.renderUserDetailRow(response);
					that.renderLoginAs();
				}
			});
		},

        /** ***********************************************************************
         *  CONSORTIA
         ** ***********************************************************************/

        renderConsortiaDetail: function(companyId){
            var that = this;

            this.addAction(this.wrapFunction(this.renderSupplierDetail, this, [companyId]));

            that.asyncRequest({
                url: '/shipmate/manage-user',
                data: {a: 'con', q: companyId},
                type: 'GET',
                cache: false,
                error: that.xhrOnError,
                success: function( response ){
                	//that.accountIsDeleted = response.accountIsDeleted;
                    that.renderConsortiaDetailRow(response);
                }
            });

        },

		renderUserDetailRow: function(data){
			var html = this.userTemplate(data);
			var that = this;

			$('.right .content').html(html);
			$('.controlBtn').hide();
			$("#saveDataBtn")
				.show()
				.unbind('click')
				.bind('click', function(){
					that.saveUserDetail();
				});

			this.renderUserCompanyMembership(data.PSU_ID);
		},

		renderLoginAs: function(){
			$('#loginas').click(function(e){
				e.preventDefault();
				if (!$('#super-password').val()) {
					alert('You need to type the super password to perform this action');
					return false;
				}
				var username = $("input[name='PSU_EMAIL']").val();
				var password = $('#super-password').val();
				$.ajax({
					url: '/shipmate/login-as',
					data: {"superpassword": password, "username":username},
					dataType: "json",
					type: 'POST',
					cache: false,
				    //error: this is handled by global ajaxError listener 
					success: function(response){
						$('.operation-in-progress-overlay').hide();
						if (typeof(response)=='object' && response.status == 'success') {
							alert('You are now logged in as "' + username + '"');
							window.location.href = '/search';
						} else {
							alert('You could not login as "' + username + '": ' + JSON.stringify(response));
						}
					}
				});				
			});
		},
		
		saveUserDetail: function(){
			var that = this;

			if( confirm('Would you like to save the changes?') ){
				$('.operation-in-progress-overlay').show();

				this.asyncRequest({
					url: '/shipmate/manage-user?b=user',
					data: $('#userForm').serialize(),
					type: 'GET',
					cache: false,
				    error: that.xhrOnError,
					success: function( response ){
						$('.operation-in-progress-overlay').hide();
                        //$('.pane .left .control form input[name="search"]').trigger('click');
                        $('.pane .left .content .active .n').html($('#userForm input[name="PSU_FIRSTNAME"]').val() + ' ' + $('#userForm input[name="PSU_LASTNAME"]').val());
                        $('.pane .left .content .active').trigger('click');

					}
				});
			}
		},

		renderUserCompanyMembership: function(userId, cb){
			var that = this;
			that.activeId = userId;
			that.asyncRequest({
				url: '/shipmate/manage-user',
				data: {a: 'user-company', q: userId},
				type: 'GET',
				cache: false,
			    error: that.xhrOnError,
				success: function( response ){
					that.renderUserCompanyMembershipRow( response, function(){
						that.renderUserActivity(userId);
					});

					// callback when finish refreshing
					if( cb )
					cb();
				}
			});
		},

		renderUserActivity: function(userId, cb){
			var that = this;
			that.activeId = userId;
			that.asyncRequest({
				url: '/shipmate/manage-user',
				data: {a: 'user-activity', q: userId},
				type: 'GET',
				cache: false,
			    error: that.xhrOnError,
				success: function( response ){
					that.renderUserActivityRow( response );

					// callback when finish refreshing
					if( cb )
					cb();
				}
			});
		},

		renderUserActivityRow: function(data){
			var count = 0;
			var that = this;
			var html = this.userActivityTemplate(data);
			
			$('.activity').html(html);
			var dataTableConfig = {
				'autoWidth': true,
		        'lengthMenu': [ [10, 50, 100], [10, 50, 100] ]
			};

			var table = $('.dataTable').DataTable(dataTableConfig);

		},


		renderUserCompanyMembershipRow: function(data, cb){
			var count = 0;
			var that = this;
			var n = {
				list: data, 
				total: data.length
			};

			var html = this.userCompanyTemplate(n);
			$('.company-membership').html(html);

			//
			$(".existing-relationship select").unbind('change').bind('change', function(){
				var companyId = $(this).closest('tr').attr('companyId');
				var data = {
					'userId': $('input[name="PSU_ID"]').val(), 
					'companyId': $(this).closest('tr').attr('companyId'), 
					'column': $(this).attr('name'), 
					'value': $(this).val()
				};

				that.asyncRequest({
					url: '/shipmate/manage-user?b=update-user-company',
					data: data,
					type: 'GET',
					cache: false,
				    error: that.xhrOnError,
					success: function( response ){
						$('.operation-in-progress-overlay').show();

						// re-rendering userCompanyMembership view
						that.renderUserCompanyMembership(that.activeId, function(){
							$('.operation-in-progress-overlay').hide();
						});
					}
				});

			});

			that.attachOperationInProgressOverlay('.company-membership table');

			$('.linkToCompany').unbind('click').bind('click', function(e){
				id = $(this).attr('dataId');
				type = $(this).attr('dataType');

				if( type == 'SPB' ){
					that.renderSupplierDetail(id);
				}else if( type == 'BYB' ){
					that.renderBuyerBranchDetail(id);
				}else if( type == 'BYO' ){
					that.renderBuyerOrganisationDetail(id);
                }else if( type == 'CON' ){
                    that.renderConsortiaDetail(id);
				}else{
					console.log(type);
				}
			});

			$(".addNewBtn").unbind('click').bind('click', function(e){
				$(".table-new-row-panel, .table-control-panel").toggle();

				$(".saveNewBtn").unbind('click').bind('click', function(e){

					if( confirm('Would you like to save the changes?') ){
						$('.operation-in-progress-overlay').show();

						var payload = {
							'PUC_PSU_ID': $('input[name="PSU_ID"]').val()
							, 'PUC_COMPANY_ID': $('input[name="NEW_PUC_COMPANY_ID"].new-entry').val()
							, 'PUC_COMPANY_TYPE': $('select[name="NEW_PUC_COMPANY_TYPE"].new-entry').val()
							, 'PUC_LEVEL': $('select[name="NEW_PUC_LEVEL"].new-entry').val()
							, 'PUC_STATUS': $('select[name="NEW_PUC_STATUS"].new-entry').val()
							, 'PUC_MATCH': $('select[name="NEW_PUC_MATCH"].new-entry').val()
							, 'PUC_IS_DEFAULT': $('select[name="NEW_PUC_IS_DEFAULT"].new-entry').val()
							, 'PUC_TXNMON': $('select[name="NEW_PUC_TXNMON"].new-entry').val()
							, 'PUC_TXNMON_ADM': $('select[name="NEW_PUC_TXNMON_ADM"].new-entry').val()
							, 'PUC_WEBREPORTER': $('select[name="NEW_PUC_WEBREPORTER"].new-entry').val()
							, 'PUC_BUY': $('select[name="NEW_PUC_BUY"].new-entry').val()
							, 'PUC_AUTOREMINDER': $('select[name="NEW_PUC_AUTOREMINDER"].new-entry').val()
						};

						that.asyncRequest({
							url: '/shipmate/manage-user?b=user-company',
							data: payload,
							type: 'GET',
							cache: false,
						    error: that.xhrOnError,
							success: function( response ){
								$('.operation-in-progress-overlay').show();

								// re-rendering userCompanyMembership view
								that.renderUserCompanyMembership(that.activeId, function(){
									$('.operation-in-progress-overlay').hide();
								});

							}
						});
					}
				});

				$(".cancelSaveNewBtn").unbind('click').bind('click', function(e){
					$(".table-new-row-panel, .table-control-panel").toggle();
                    $("input[name='newCompanyInput'], select[name='NEW_PUC_COMPANY_TYPE'], select[name='NEW_PUC_LEVEL'], select[name='NEW_PUC_STATUS'], select[name='NEW_PUC_MATCH'], select[name='NEW_PUC_IS_DEFAULT'], select[name='NEW_PUC_TXNMON'], select[name='NEW_PUC_WEBREPORTER'], select[name='NEW_PUC_BUY'], select[name='NEW_PUC_APPROVED_SUPPLIER'], select[name='NEW_PUC_TXNMON_ADM'], select[name='NEW_PUC_AUTOREMINDER']").val("");
                    $("#add-new-form").trigger('reset');
				});
			});

			$(".saveBtn").unbind('click').bind('click', function(e){
				var params = $(this).closest("form").serialize();

				that.asyncRequest({
					url: '/shipmate/manage-user?b=update-user-company',
					data: params,
					type: 'POST',
					cache: false,
				    error: that.xhrOnError,
					success: function( response ){
						$('.operation-in-progress-overlay').show();

						// re-rendering userCompanyMembership view
						that.renderUserCompanyMembership(that.activeId, function(){
							$('.operation-in-progress-overlay').hide();
						});

					}
				});
				$('.operation-in-progress-overlay').show();
				that.renderUserCompanyMembership(that.activeId, function(){
					$('.operation-in-progress-overlay').hide();
				});
			});

			that.autoCompleteCallBack = function (data){
				$('input[name="NEW_PUC_COMPANY_ID"]').val(data.data);
				return true;
			};

			that.transformationCallback = function(response) {
            	var result = [];
            	response = $.parseJSON(response);
            	$.each(response, function(n, d) {
            		var display = d.value + ' (' + d.pk + ')';
            		result.push({ value: display, data: d.pk });
                });

                return {
                    suggestions: result
                };
            };

			// hide and show match additional checkboxes
			// depending on the company type, it'll show different kinds of auto complete
			$('select[name="NEW_PUC_COMPANY_TYPE"]').unbind('change').bind('change', function() {

				var companyType = $(this).val();

				if (companyType == 'BYB') {

					$('.BYB_ONLY_FEATURE').show();
					window.autoCompleteUrl = '/search/autocomplete/buyer';

					that.renderAutoComplete(
						'input[name="newCompanyInput"]',
						'/profile/company-search/format/json/type/bb/excUsrComps/1/excNonJoinReqComps/1/',
						that.autoCompleteCallBack,
						that.transformationCallback
					);


				} else if (companyType == 'SPB'){

					$('.BYB_ONLY_FEATURE').hide();
					$('.BYB_ONLY_FEATURE').val('');
					window.autoCompleteUrl = '/search/autocomplete/supplier';

					that.renderAutoComplete(
						'input[name="newCompanyInput"]',
						'/profile/company-search/format/json/type/v/excUsrComps/1/excNonJoinReqComps/1/',
						that.autoCompleteCallBack,
						that.transformationCallback
					);

				} else if (companyType == 'BYO'){

					$('.BYB_ONLY_FEATURE').hide();
					$('.BYB_ONLY_FEATURE').val('');

					that.renderAutoComplete(
						'input[name="newCompanyInput"]',
						'/profile/company-search/format/json/type/b/excUsrComps/1/excNonJoinReqComps/1/',
						that.autoCompleteCallBack,
						that.transformationCallback
					);
				} else if (companyType == 'CON'){

                    $('.BYB_ONLY_FEATURE').hide();
                    $('.BYB_ONLY_FEATURE').val('');

                    that.renderAutoComplete(
                        'input[name="newCompanyInput"]',
                        '/profile/company-search/format/json/type/c/excUsrComps/1/excNonJoinReqComps/1/',
                        that.autoCompleteCallBack,
                        that.transformationCallback
                    );
                }
			});

			if(cb) cb();
		},


		/** ***********************************************************************
		 *  RELATIONSHIP BETWEEN AN COMPANY (BYO, BYB, SPB) WITH USER
		 ** ***********************************************************************/
		renderCompanyUser: function(id, type, cb){
			var that = this;
			that.activeId = id;
            that.activeType = type;

			that.asyncRequest({
				url: '/shipmate/manage-user',
				data: {a: 'company-user', q: id, t: type},
				type: 'GET',
				cache: false,
			    error: that.xhrOnError,
				success: function( response ){
					that.renderUserCompanyUserRow( response, type, cb );
				}
			});
		},

		/**
		 * Rendering list of user within the company view
		 */
		renderUserCompanyUserRow: function(data, type, cb){
			var that = this;
			var n = {
				list: data, 
				total: data.length, 
				companyId: that.activeId, 
				companyType: that.activeType.toUpperCase(),
				accountIsDeleted: that.accountIsDeleted
			};

			// rendering template
			var html = this.companyUserTemplate(n);
			$('.company-membership').html(html);

			// binding onChange to save the changes made
			$(".existing-relationship select").unbind('change').bind('change', function(){
				var companyId = $(this).closest('tr').attr('companyId');
				var data = {
					'userId': $(this).closest('tr').attr('userId'), 
					'companyId': $(this).closest('tr').attr('companyId'), 
					'column': $(this).attr('name'), 
					'value': $(this).val()
				};

				that.asyncRequest({
					url: '/shipmate/manage-user?b=update-user-company',
					data: data,
					type: 'GET',
					cache: false,
				    error: that.xhrOnError,
					success: function( response ){
						//$('.operation-in-progress-overlay').show();

						// re-rendering userCompanyMembership view
						that.renderCompanyUser(that.activeId, type, function(){
							//$('.operation-in-progress-overlay').hide();
						});
					}
				});

			});

			that.attachOperationInProgressOverlay('.company-membership table');

			// for each user, when clickable, this will show user detail on the right panel
			$('.linkToUser').unbind('click').bind('click', function(){
				that.renderUserDetail($(this).attr('dataId'));
			});

			// action when add new button is clicked
			$(".addNewBtn").unbind('click').bind('click', function(e){

				// show the new form
				$(".table-new-row-panel, .table-control-panel").toggle();

				// tell the autocomplete the URL of the AJAX end point
				window.autoCompleteUrl = '/search/autocomplete/user';

				// callback that he autoComplete need to call when item's clicked
				that.autoCompleteCallBack = function (data){
					$('input[name="newUserName"]').val(data.email);
					$('input[name="PSU_ID"]').val(data.data);
					$('input[name="newUserFirstLastName"]').val(data.name);
					return true;
				};

				// transforming result from endpoint
				that.transformationCallback = function(response) {
	            	var result = [];
	            	response = $.parseJSON(response);
	            	$.each(response, function(n, d) {
	            		result.push({ value: d.TITLE + ' (' + d.PSU_EMAIL + ')', name: d.TITLE, data: d.ID, email: d.PSU_EMAIL });
	                });
	                return {
	                    suggestions: result
	                };
	            };


				// render/bind auto complete to a specific control
				that.renderAutoComplete(
					'input[name="newUserFirstLastName"]',
					'/shipmate/manage-user?a=search&a=search&t=usr',
					that.autoCompleteCallBack,
					that.transformationCallback
				);

				$(".saveNewBtn").unbind('click').bind('click', function(e){
					if( confirm("Are you sure you want to add this new user?") ){

						// show overlay/progress
						$('.operation-in-progress-overlay').show();

                        var payload = {
                          'PUC_PSU_ID': $('input[name="PSU_ID"]').val()
                          , 'PUC_COMPANY_ID': $('input[name="NEW_PUC_COMPANY_ID"].new-entry').val()
                          , 'PUC_COMPANY_TYPE': $('input[name="NEW_PUC_COMPANY_TYPE"].new-entry').val()
                          , 'PUC_LEVEL': $('select[name="NEW_PUC_LEVEL"].new-entry').val()
                          , 'PUC_STATUS': $('select[name="NEW_PUC_STATUS"].new-entry').val()
                          , 'PUC_MATCH':              $('select[name="NEW_PUC_MATCH"].new-entry').length > 0 ? $('select[name="NEW_PUC_MATCH"].new-entry').val():0
                          , 'PUC_IS_DEFAULT':         $('select[name="NEW_PUC_MATCH"].new-entry').length > 0 ? $('select[name="NEW_PUC_IS_DEFAULT"].new-entry').val():0
                          , 'PUC_TXNMON':             $('select[name="NEW_PUC_MATCH"].new-entry').length > 0 ? $('select[name="NEW_PUC_TXNMON"].new-entry').val():0
                          , 'PUC_TXNMON_ADM':  $('select[name="NEW_PUC_MATCH"].new-entry').length > 0 ? $('select[name="NEW_PUC_TXNMON_ADM"].new-entry').val():0
                          , 'PUC_WEBREPORTER':        $('select[name="NEW_PUC_MATCH"].new-entry').length > 0 ? $('select[name="NEW_PUC_WEBREPORTER"].new-entry').val():0
                          , 'PUC_BUY':                $('select[name="NEW_PUC_MATCH"].new-entry').length > 0 ? $('select[name="NEW_PUC_BUY"].new-entry').val():0
                          , 'PUC_AUTOREMINDER':  $('select[name="NEW_PUC_MATCH"].new-entry').length > 0 ? $('select[name="NEW_PUC_AUTOREMINDER"].new-entry').val():0
                        };

                        that.asyncRequest({
                          url: '/shipmate/manage-user?b=user-company',
                          data: payload,
                          type: 'GET',
                          cache: false,
                            error: that.xhrOnError,
                          success: function( response ){
                            $('.operation-in-progress-overlay').show();

                            // re-rendering userCompanyMembership view
                            that.renderCompanyUser(that.activeId, that.activeType, function(){
                              $('.operation-in-progress-overlay').hide();
                            });
                          }
                        });
					}
				});

				$(".cancelSaveNewBtn").unbind('click').bind('click', function(e){
					$(".table-new-row-panel, .table-control-panel").toggle();
                    $("input[name='newUserFirstLastName'], select[name='NEW_PUC_LEVEL'], select[name='NEW_PUC_STATUS']").val("");
				});
			});
			if(cb) cb();
		},

		asyncRequest: function(params){

	        if (this.xhr && this.xhr.readyState != 4) {
	        	this.xhr.abort();
	        }

	        params.beforeSend = function(){
		        // telling user that request is in progress
		        $("#xhrProgress").html('loading...').fadeIn(1000);
	        };

	        // when this is done, then hide the progress
	        params.complete = function(){
	        	$("#xhrProgress").fadeOut(400);
	        };

	        params.abort = function(){
	        	$("#xhrProgress").hide();
	        };

	        // when this is done, then hide the progress
	        params.error = function(){
	        	$("#xhrProgress").html('Problem completing task...').fadeIn(400);
	        };

			this.xhr = $.ajax(params);

		},

		attachOperationInProgressOverlay: function(element){

			var table = $(element);

			if (table.prev().hasClass('operation-in-progress-overlay') === false) {
				table.parent().prepend('<div class="operation-in-progress-overlay"><div>Please wait...</div></div>');
			}

			$('.operation-in-progress-overlay').css({
				width: table.css('width'),
				width: table.css('width'), 
				height: table.css('height')
			});
		},

		filterForInput: function(s){
			return (typeof s == 'object' && s === null ) ? '':s;
		},

		renderAutoComplete: function(inputBoxElement, urlForAutoComplete, onSelectCb, transformationCb){

			if( $(inputBoxElement).autocomplete() === false )
			$(inputBoxElement).autocomplete().dispose();

 			$(inputBoxElement).autocomplete({
				paramName: 'value',
 				serviceUrl: urlForAutoComplete,
 				width:665,
                zIndex: 9999,
                minChars: 3,
                noCache: true,
                transformResult: transformationCb,
                onSearchStart: function() {
                	$(".tnidAutocomplete").show();
                },
                onSearchStop: function() {
                	$(".tnidAutocomplete").hide();
                },
                onStart: function(){
                    $('.keywordsAutocomplete').css('display', 'inline-block');
                },
                onFinish: function(){
                    $('.keywordsAutocomplete').hide();
                	$(".tnidAutocomplete").hide();
                },
                onSelect: onSelectCb,
                onSearchError: function(query, jqXHR, textStatus, errorThrown){
                	if (jqXHR.responseText) {
	                	var response=jQuery.parseJSON(jqXHR.responseText);
						if(typeof response =='object'){
						  //It is JSON
						  if (response.error === true) {
						  	thisView.parent.endless = false;
	                    	$('.keywordsAutocomplete').hide();
	                    	$(".tnidAutocomplete").hide();
						  	alert(response.message);
						  }
						}
					}
                }
			});
		},

		getByoStructure: function(companyId){

			if ($('#orgStrucBtn').hasClass('disabled')) {
				$('#orgStrucBtn').removeClass('disabled');
				$('#orgStrucContainer').slideUp();
			} else {

				$('#orgStrucBtn').addClass('disabled');

				var that = this;
				$('.fa-spinner').show();
				that.asyncRequest({
					url: '/shipmate/manage-user',
					data: {a: 'byo-struc', byo: companyId},
					type: 'GET',
					cache: false,
				    error: that.xhrOnError,
					success: function( response ){
						that.renderByoStructure(response,$('#orgStrucContainer'));

						$('.linkToByb').unbind('click').bind('click', function(){
							that.renderBuyerBranchDetail($(this).attr('dataId'));
						});

						$('#orgStrucContainer').slideDown(400,function(){
							$('.fa-spinner').hide();
						});
					}
				});
			}

		},

		renderByoStructure: function(response, appendTo)
		{
			var that = this;
    		var rootElement = null;

			for (var key in response) {
				if (rootElement === null) {
					rootElement = $('<ul>');
				}
				var liElement = $('<li>');
				if (response[key].type === 'org') {
					var spanElement = $('<span>');
					spanElement.addClass('search-link');
					spanElement.attr('datatype','buyer-org');
					spanElement.attr('dataid',key);
					spanElement.html(key + ' ' + response[key].name);
					liElement.append(spanElement);
				} else {
					var aElement = $('<a>');
					aElement.html(key + ' ' + response[key].name);
					aElement.addClass('linkToByb');
					aElement.attr('dataid',key);
					liElement.append(aElement);
				}

				that.renderByoStructure(response[key].branches,liElement);
				rootElement.append(liElement);
			}
			
			if (rootElement !== null) {
				$(appendTo).append(rootElement);
			}

		},

		writeToRightPanel: function(html){
			var o = '';
			o += '<div style="padding:10px;">';
				o += html;
			o += '</div>';
			$('.right .content').html(o);
		},

		writeToLeftPanel: function(html){
			var o = '';
			o += '<div style="padding:10px;">';
				o += html;
			o += '</div>';

			$('.left .content').html(o);
		},



		ucwords: function(str) {
		  //  discuss at: http://phpjs.org/functions/ucwords/
		  // original by: Jonas Raoni Soares Silva (http://www.jsfromhell.com)
		  // improved by: Waldo Malqui Silva (http://waldo.malqui.info)
		  // improved by: Robin
		  // improved by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
		  // bugfixed by: Onno Marsman
		  //    input by: James (http://www.james-bell.co.uk/)
		  //   example 1: ucwords('kevin van  zonneveld');
		  //   returns 1: 'Kevin Van  Zonneveld'
		  //   example 2: ucwords('HELLO WORLD');
		  //   returns 2: 'HELLO WORLD'

		return (str + '')
		    .replace(/^([a-z\u00E0-\u00FC])|\s+([a-z\u00E0-\u00FC])/g, function ($1) {
		      return $1.toUpperCase();
		    });
		},
		
		sprStatusChange: function(e) {
			var $status = ($(e.currentTarget).is(':checked')) ? 1 : 0;
			var $id = $(e.currentTarget).data('id');
			this.changeSprStatus($id, $status);
		},
		
		changeSprStatus: function(id, status) {
			$.ajax({
				type: 'POST',
				url: '/shipmate/change-byo-spr-access-status', 
				data: {id: id, status: status},
				beforeSend: function(){
					$("#xhrProgress").html('submitting...').fadeIn(500);
				}
			})
			.fail(function(response){ 
				$("#xhrProgress").html('http failure. see console errors').fadeOut(3000);
				console.log(response);
			})
			.done(function(response){
				if (response && response.status==='success') {
					$("#xhrProgress").html('successfully updated').fadeOut(1000);
				} else {
					$("#xhrProgress").html('http failure. see console errors').fadeOut(3000);
					console.log(response.description);
				} 
			});
		},
		
		membershipLevelChange: function(e) {
			var $status = ($(e.currentTarget).val());
			var $id = $(e.currentTarget).data('id');
			this.changeMembershipStatus($id, $status);
		},
		
		changeMembershipStatus: function(id, status) {
			$.ajax({
				type: 'POST',
				url: '/shipmate/change-byo-membership-status', 
				data: {id: id, status: status},
				beforeSend: function(){
					$("#xhrProgress").html('submitting...').fadeIn(500);
				}
			})
			.fail(function(response){ 
				$("#xhrProgress").html('http failure. see console errors').fadeOut(3000);
				console.log(response);
			})
			.done(function(response){
				if (response && response.status==='success') {
					$("#xhrProgress").html('successfully updated').fadeOut(1000);
				} else {
					$("#xhrProgress").html('http failure. see console errors').fadeOut(3000);
					console.log(response.description);
				} 
			});
		}

	});

	return new mainView();
});
