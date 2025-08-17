
/* global blocks */

//if (blocks != null && blockCode != null && blockName != null && blockValue != null) {
//    
//}

var total = 0;
var rowsNumber = 0;

function updateSubmitButtonStatus() {
    if (total * 1 === 100) {
        $("#submit").removeAttr("disabled");
        $("#submit").html("Guardar cambios");
    } else {
        $("#submit").attr("disabled", "disabled");
        $("#submit").html("La ponderación debe sumar 100 puntos para guardar");
    }
    $("#total").html(total);
}

function updateTable() {
    html = "";
    total = 0;
    countRows = false;
    if (rowsNumber === 0) {
        countRows = true;
    }
    Object.keys(blocks).forEach(function (blockCode) {
        if (countRows) {
            rowsNumber++;
        }
        block = blocks[blockCode];
        total = total + parseFloat(block[blockValueElement]);
        error = block["error"];
        if (!error) {
            error = "";
        }
        if (parseInt(block["notas"]) != 0) {
            html +=
                    `<tr style = "background-color: #e8e8e8 !important;">
                        <input type="hidden" name="${blockCodeElement}[]"  value="${block[blockCodeElement]}"/>
                        <input type="hidden" name="${blockNameElement}[]"  value="${block[blockNameElement]}"/>
                        <input type="hidden" name="${blockValueElement}[]"  value="${block[blockValueElement]}"/>
                        <td style="vertical-align:middle;">
                            ${block[blockNameElement]}
                            <font color="red">
                                ${error}
                            </font>
                        </td>
                        <td style="vertical-align:middle;text-align: center;">
                            ${block[blockValueElement]}
                        </td>
                        <td style="vertical-align:middle;text-align: center;">
                            <font color="gray">
                            <i class="fa fa-lock"></i> Con notas
                            </font>
                        </td>
                    </tr>`;
        } else {
            html +=
                    `<tr>
                        <input type="hidden" name="${blockCodeElement}[]"  value="${block[blockCodeElement]}"/>
                        <td style="vertical-align:middle;">
                            <input type="text" class="form-control ${blockNameElement}" name="${blockNameElement}[]"  value="${block[blockNameElement]}" blockCode="${block[blockCodeElement]}"/>
                            `//${block[blockNameElement]}
                    + `
                            <font color="red" id="${blockNameElement}Error${blockCode}">
                                ${error}
                            </font>
                        </td>
                        <td style="vertical-align:middle;text-align: center;">
                        <input type="text" class="form-control ${blockValueElement}" name="${blockValueElement}[]"  value="${block[blockValueElement]}" blockCode="${block[blockCodeElement]}"/>
                            `//${block[blockValueElement]}
                    + `
                            <font color="red" id="${blockValueElement}Error${blockCode}">
                            </font>
                        </td>
                        <td style="vertical-align:middle;text-align: center;">
                            <button type="button" class="btn btn-red remove-block" 
                                    blockCode="${block[blockCodeElement]}">
                                <i class="fa fa-minus"></i>
                            </button>    
                        </td>
                    </tr>`;
        }
    });
    updateSubmitButtonStatus();
    $("#blockRows").html(html);
}

$('#add').on('click', function () {
    name = $("#" + blockNameElement).val();
    value = $("#" + blockValueElement).val();
    code = "N" + (rowsNumber + 1);
    rowsNumber++;
    valid = true;
    if (value === "") {
        $("#valueError").html("El punteo no debe estar vacío");
        valid = false;
    } else if (isNaN(value)) {
        $("#valueError").html("El valor debe ser numérico");
        valid = false;
    } else if (parseFloat(value).toFixed(2) * 1 === 0) {
        $("#valueError").html("El valor debe mayor a 0");
        valid = false;
    } else if ((parseFloat(total) * 1 + parseFloat(value).toFixed(2) * 1) > 100) {
        $("#valueError").html("El punteo debe ser menor a " + (100 - total) + " para sumar los 100 puntos.");
        valid = false;
    } else {
        $("#valueError").html("");
    }
    if (name === "") {
        $("#nameError").html("El nombre no debe estar vacío");
        valid = false;
    } else {
        $("#nameError").html("");
    }
    if (valid) {
        block = {};
        block[blockCodeElement] = code;
        block[blockNameElement] = name;
        value = (parseFloat(value).toFixed(2));
        block[blockValueElement] = "" + value;
        block["notas"] = 0;
        blocks[code] = block;
        $("#" + blockNameElement).val("");
        if ((total * 1 + value * 1) >= 100) {
            pending = "";
        } else {
            pending = "" + (100 - (total * 1 + value * 1));
        }
        $("#" + blockValueElement).val(pending);
        updateTable();
        console.log("bloque: \"" + name + "\" de " + value + " puntos agregado.");
    }
});

$("#tableDiv").on('click', '.remove-block', function () {
    blockCode = $(this).attr("blockCode");
    $("#" + blockValueElement).val(100 - (total - blocks[blockCode][blockValueElement] * 1));
    console.log("Bloque de código: " + blockCode + " eliminado: " + JSON.stringify(blocks[blockCode]));
    delete blocks[blockCode];
    updateTable();
});

$("#tableDiv").on('change', '.' + blockValueElement, function () {
    blockCode = $(this).attr("blockCode");
    value = $(this).val();
    errorElement = $("#" + blockValueElement + "Error" + blockCode);
    if (value === "") {
        errorElement.html("No debe estar vacío");
        $(this).val(blocks[blockCode][blockValueElement]);
    } else if (isNaN(value)) {
        errorElement.html("Debe ser numérico");
        $(this).val(blocks[blockCode][blockValueElement]);
    } else {
        errorElement.html("");
        value = (parseFloat(value).toFixed(2)) * 1;
        blocks[blockCode][blockValueElement] = value;
        $(this).val(value);
        total = 0;
        Object.keys(blocks).forEach(function (blockCode) {
            block = blocks[blockCode];
            total = total + parseFloat(block[blockValueElement]);
        });
        $("#" + blockValueElement).val(100 - total);
        updateSubmitButtonStatus();
    }
});

$("#tableDiv").on('change', '.' + blockNameElement, function () {
    blockCode = $(this).attr("blockCode");
    text = $(this).val();
    errorElement = $("#" + blockNameElement + "Error" + blockCode);
    if (text === "") {
        errorElement.html("No debe estar vacío");
        $(this).val(blocks[blockCode][blockNameElement]);
    } else {
        errorElement.html("");
        blocks[blockCode][blockNameElement] = text;
    }
});

updateTable();
