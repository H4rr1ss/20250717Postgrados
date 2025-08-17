
function updateTotalGrade(userCode) {
    total = 0;
    hasValues = false;
    $('.user_' + userCode).each(function (i, obj) {
        value = $(this).val();
        if (value !== "") {
            hasValues = true;
            total += value * 1;
        }
    });
    if (!hasValues) {
        total = "-";
    }
    total = Math.round(total);
    $("#nota_final_" + userCode).html(total);
    if (!isNaN(minToApproval) && !isNaN(total)) {
        if (total * 1 >= minToApproval) {
            color = "green";
        } else {
            color = "red";
        }
        $("#nota_final_" + userCode).attr("color", color);
    } else {
        $("#nota_final_" + userCode).attr("color", "black");
    }
    $("#completeEntryDiv").hide();
}


$("#tableDiv").on('change', '.grade', function () {
    blockCode = $(this).attr("blockCode");
    userCode = $(this).attr("userCode");
    elName = "#" + userCode + "_" + blockCode;
    value = $(this).val();
    if (isNaN(value)) {
        $(this).val("0");
        $(elName + "Error").html("Debe ser númerico");
    } else {
        if (value !== "") {
            limit = blocks[blockCode]["valor"] * 1;
            if (value > limit) {
                $(elName + "Error").html("Debe ser menor al límite (" + limit + ")");
                value = limit;
            } else {
                $(elName + "Error").html("");
            }
            value = parseFloat(value).toFixed(2) * 1 + " ";
        }
        grades[userCode]["grades"][blockCode]["grade"] = value * 1;
        $(this).val(value);
    }
    updateTotalGrade(userCode);
});

$('.attendance').on('ifClicked', function (event) {
    checked = !this.checked;
    userCode = $(this).attr("userCode");
    if (!checked) {
        Object.keys(blocks).forEach(function (blockCode) {
            element = $("#" + userCode + "_" + blockCode);
            previous = element.val();
            if (previous !== "") {
                element.val("0");
            }
            $(element).attr("readonly", true);
        });
    } else {
        Object.keys(blocks).forEach(function (blockCode) {
            element = $("#" + userCode + "_" + blockCode);
            element.removeAttr("readonly");
            grade = grades[userCode]["grades"][blockCode]["grade"];
            if (!grade) {
                grade = "";
            }
            element.val(grade);
        });
    }
    updateTotalGrade(userCode);
});