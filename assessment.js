/* JavaScript file for Assessment module */

var assessment = {};

M.mod_assessment = {};

M.mod_assessment.init_next = function(Y, nextid, workmode, usertograde) {
    assessment.nextid = nextid;
	assessment.workmode = workmode;
	assessment.usertograde = usertograde;
};

function setMenuIndex () {
    var submitform = document.getElementById('submitform');
    submitform.menuindex.value = submitform.grade.selectedIndex;
}

function setNext() {
    if (assessment.workmode == 'group') {
        var param = '&groupid='+assessment.nextid;
    } else {
        var param = '&userid='+assessment.nextid;
    }
    self.location = 'assessment_grades.php?id='+document.getElementById('submitform').id.value+
                    param+
                    '&offset='+document.getElementById('submitform').offset.value+
                    '&markergroupid='+document.getElementById('submitform').markergroupid.value+
                    '&type='+document.getElementById('submitform').type.value+
                    '&mode=single';
}

function saveNext() {
    document.getElementById('submitform').mode.value = 'saveandnext';
    if (assessment.workmode == 'group') {
        document.getElementById('submitform').groupid.value = assessment.nextid;
    } else {
        document.getElementById('submitform').userid.value = assessment.nextid;
    }
    document.getElementById('submitform').saveuserid.value = assessment.usertograde;
    document.getElementById('submitform').menuindex.value = document.getElementById('submitform').grade.selectedIndex;
}

function view_comment(view) {
    if (view) {
        document.getElementById("div_view_comment").className = "generalbox";
        document.getElementById("div_edit_comment").style.display = "none";
    } else {
        document.getElementById("div_view_comment").className = "generalbox div_hide";
        document.getElementById("div_edit_comment").style.display = "";
    }
}

function initRubricStr(Y, hiderubric, showrubric) {
    assessment.hiderubric = hiderubric;
    assessment.showrubric = showrubric;
}

function showhiderubric(divid, link, attr) {
    var targetdiv = document.getElementById(divid);
    if (targetdiv.style.display == "none") {
        targetdiv.style.display = "";
        if (link) {
            if (attr == "title") {
                link.title = assessment.hiderubric;
            } else if (attr == "innerHTML") {
                link.innerHTML = assessment.hiderubric;
            }
        }
    } else {
        targetdiv.style.display = "none";
        if (link) {
            if (attr == "title") {
                link.title = assessment.showrubric;
            } else if (attr == "innerHTML") {
                link.innerHTML = assessment.showrubric;
            }
        }
    }
}

function change_link_type(link_type, link_count) {
    if (link_type == "file") {
        document.getElementById("FILE_"+link_count).style.display = "";
        document.getElementById("URL_"+link_count).disabled = true;
        document.getElementById("URL_"+link_count).style.display = "none";
    } else if (link_type == "url") {
        document.getElementById("FILE_"+link_count).style.display = "none";
        document.getElementById("URL_"+link_count).disabled = false;
        document.getElementById("URL_"+link_count).style.display = "";
    }
}

function initDeleteStr(canceldelete, deletestr) {
    assessment.canceldelete = canceldelete;
    assessment.deletestr = deletestr;
}

function deleteFile(linkname) {
    isDelete = document.getElementById(linkname).value;
    linknamesplit = linkname.split("_");
    linknamesplit.shift();
    filename = linknamesplit.join("_");

    if (isDelete == "1") {
        document.getElementById(linkname).value = "0";
        document.getElementById("deletelink_"+filename).innerHTML = assessment.canceldelete;
        document.getElementById(filename+"_linkstr").style.textDecoration = "line-through";
    } else {
        document.getElementById(linkname).value = "1";
        document.getElementById("deletelink_"+filename).innerHTML = ssessment.deletestr;
        document.getElementById(filename+"_linkstr").style.textDecoration = "none";
    }
}