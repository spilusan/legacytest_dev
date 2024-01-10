$(document).ready(function() {

	function openFile (file) 
	{ 
		$.get('/supplier/catalogue/format/html/',
		{
			catId: (file),
			searchStart: 0,
			searchRows: 10
		},
		function(data){
			$(".content_body").html(data);
		});
		$(".file").removeClass('expanded_file').addClass('collapsed');
		$("#"+(file)).removeClass('collapsed').addClass('expanded_file');
	};
	
	$('#sidebar_nav').fileTree(
		{ root: '',
		  script: "/supplier/list/format/html/tnid/<?echo $this->supplier['tnid'];?>",
		  folderEvent: 'click',
		  expandSpeed: 750,
		  collapseSpeed: 750,
		  multiFolder: false },
		  function(file) { 
			openFile(file);
		}
	);
 });