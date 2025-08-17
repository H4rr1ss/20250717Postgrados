/* GLOBALLY CREATED VARIABLES
 * "users" WITH USER_DATA
 * "addId" WITH THE ADD ID FOR THE BUTTON THAT WILL ADD THE USER
 * "submit" WITH THE SUBMIT BUTTON NAME
 * "tableBody" WITH THE TABLE BODY NAME
 * "userColumnId" WITH THE NAME THAT THE HIDDEN USER_CODE ELEMENT SHOULD HAVE
 */
var total = 0;
var rowsNumber = 0;

function updateTable() {
    html = "";
    total = 0;
    countRows = false;
    if (rowsNumber === 0) {
        countRows = true;
    }
    Object.keys(users).forEach(function (userCode) {
        user = users[userCode];
        if (user["added"] == true) {
            $("#" + userCode).attr("disabled", "disabled");
            html +=
                    `<tr>
                    <input type="hidden" name="${userColumnId}[]"  value="${userCode}"/>
                    <td style="vertical-align:middle;text-align: center;">
                        ${user["acadReg"]}
                    </td>
                    <td style="vertical-align:middle;">
                        ${user["names"]}
                    </td>
                    <td style="vertical-align:middle;">
                        ${user["lastNames"]}
                    </td>
                    <td style="vertical-align:middle;text-align: center;">
                        ${user["finalGrade"]}
                    </td>
                    <td style="vertical-align:middle;text-align: center;">
                        ${user["ballot"]}
                    </td>
                    <td style="vertical-align:middle;text-align: center;">
                        <textarea rows="2" name="${commentName}[]" class="form-control">${user["comment"]}</textarea>
                    </td>
                    <td style="vertical-align:middle;text-align: center;">
                        <button class="btn btn-danger removeUser" userCode="${userCode}">
                            <i class="fa fa-minus"></i>
                        </button>
                    </td>
                </tr>`;
            users[userCode]["comment"] = "";
        } else {
            $("#" + userCode).removeAttr("disabled");
        }
    });
    $(tableBody).html(html);
    if (html !== "") {
        $(submit).removeAttr("disabled");
    } else {
        $(submit).attr("disabled", "disabled");
    }
}

$(addId).on('click', function () {
    userCode = $(this).attr("userCode");
    users[userCode]["added"] = true;
    updateTable();
});

$("#tableDiv").on('click', '.removeUser', function () {
    userCode = $(this).attr("userCode");
    users[userCode]["added"] = false;
    updateTable();
});

updateTable();
