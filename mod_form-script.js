/* called by ../mod_form.php; used to control assessment editing */

// DOM element object -> rubric's select dropdown
var ru = document.getElementById('id_rubricid');

// Allows Rubric & Points select dropdown's to interact
function updateElem(value, courseid, wwwpath, sesskey) {
    var ob = document.getElementById('id_modgrade_type'); // old 'points' grading dropdown
    var i = ru.selectedIndex;
    var l = ru.options.length - 2;

    if (i < l && (!isNumeric(value) || value == 0)) {
        ob.disabled = false;
    } else {
        ob.disabled = true;
    }
    
    if (value == 'import') {
        ru.selectedIndex = 0;
        window.open(wwwpath + '/mod/assessment/rubric/mod.php?course=' + courseid + '&action=popuplistview&sesskey=' + sesskey, 'import', 'location=1,status=1,scrollbars=1,width=1024,height=640');
    } else if (value == 'new') {
        ru.selectedIndex = 0;
        window.open(wwwpath + '/mod/assessment/rubric/mod.php?course=' + courseid + '&action=popupcreate&sesskey=' + sesskey, 'new', 'location=1,status=1,scrollbars=1,width=1024,height=640');
    }
}

function changeMode(mode) {
   if (mode == 0) {
      window.location = window.location+'&mode=0';
   } else {
      var myStr=new String(window.location);
      window.location = myStr.replace('&mode=0', '');
   }
}

// Is called from popup windows after adding new rubrics
function addRubric(text, value){
    ru.options[0] = new Option(text,value); 
    ru.selectedIndex = 0;
    updateElem(value);
}

function isNumeric(num){
    var x = (isNaN(num) || num == null);
    var y = (num.toString() == 'true' || num.toString() == 'false');
    return !(x || y);
}
