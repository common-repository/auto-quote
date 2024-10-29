jQuery(document).ready(function () {
  jQuery('#checkBtn').click(function() {
    checked = jQuery("input[type=checkbox]:checked").length;
    if(!checked) {
      alert("You must select at least one service.");
      return false;
    }
  });
});