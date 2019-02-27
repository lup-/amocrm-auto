function loadApiData(data) {
    let promise = $.Deferred();

    $.ajax({
        url: "/amo.php",
        data: data,
        dataType: 'json',
        success: function (result) {
            promise.resolve(result);
        },
        error: function (result) {
            if (result && result.status === 200) {
                promise.resolve(result.responseText);
            }
            else {
                promise.reject(result);
            }
        }
    });

    return promise;
}

function loadInstructors() {
    return loadApiData({type: 'instructors'});
}

function loadHours(instructorId) {
    return loadApiData({
        type: 'getHours',
        instructorId: instructorId
    });
}

function loadLead(leadId) {
    return loadApiData({
        type: 'getLead',
        leadId: leadId
    });
}

function loadVideoLinks() {
    return loadApiData({type: 'getVideo'})
}

function loadTicketLinks() {
    return loadApiData({type: 'getTickets'})
}

function getParameterByName(name, url) {
    if (!url) url = window.location.href;
    name = name.replace(/[\[\]]/g, '\\$&');
    var regex = new RegExp('[?&]' + name + '(=([^&#]*)|&|#|$)'),
        results = regex.exec(url);
    if (!results) return null;
    if (!results[2]) return '';
    return decodeURIComponent(results[2].replace(/\+/g, ' '));
}

function getFromHTML(leadId, hours) {
    return "<form>\n" +
        "    <input type=\"hidden\" name=\"type\" value=\"updateHours\">\n" +
        "    <input type=\"hidden\" name=\"leadId\" value=\""+leadId+"\">\n" +
        "    <div class=\"form-row align-items-center\">\n" +
        "        <div class=\"col\">\n" +
        "            <label class=\"sr-only\" for=\"hoursInput-"+leadId+"\">Накатано часов</label>\n" +
        "            <input type=\"text\" name=\"hours\" class=\"form-control mb-2\" id=\"hoursInput-"+leadId+"\" placeholder=\"\" value=\""+hours+"\">\n" +
        "        </div>\n" +
        "        <div class=\"col-auto\">\n" +
        "            <button type=\"submit\" class=\"btn btn-primary mb-2\" data-lead-id=\""+leadId+"\">Сохранить</button>\n" +
        "        </div>\n" +
        "    </div>\n" +
        "</form>";
}

function getCardHTML(name, leadId, hours, neededHours, debt, phone) {
    return "<div class=\"card\">\n" +
        "    <div class=\"card-header\" id=\"heading-"+leadId+"\">\n" +
        "        <h5 class=\"mb-0\">\n" +
        "            <button class=\"btn btn-link\" type=\"button\" data-toggle=\"collapse\" data-target=\"#collapse-"+leadId+"\" aria-expanded=\"true\" aria-controls=\"collapse-"+leadId+"\">\n" +
        "                "+name+"\n" +
        "            </button>\n" +
        "        </h5>\n" +
        "    </div>\n" +
        "\n" +
        "    <div id=\"collapse-"+leadId+"\" class=\"collapse\" aria-labelledby=\"heading-"+leadId+"\" data-parent=\"#leadsAccordion\">\n" +
        "        <div class=\"card-body\">\n" +
        "           <p class='mb-0'>Остаток оплаты: " + debt + "</p>\n" +
        "           <p class='mb-0'>Нужное кол-во часов: " + neededHours + "</p>\n" +
        "           <p class=''>Телефон: <a href='tel:"+phone+"'>"+phone+"</a></p>\n" +
                    getFromHTML(leadId, hours) +
        "        </div>\n" +
        "    </div>\n" +
        "</div>";
}

function getLeadFieldHTML(fieldName, fieldValue) {
    return "<div class=\"list-group-item flex-column align-items-start\">\n" +
        "    <h5 class=\"mb-1\">"+fieldName+"</h5>\n" +
        "    <p class=\"mb-1\">"+fieldValue+"</p>\n" +
        "</div>";
}

function getComplexFieldHTML(fieldName, fieldValue) {
    let subFieldNames = Object.keys(fieldValue);
    let titleFieldName = subFieldNames[0];
    let titleFieldValue = fieldValue[titleFieldName];

    return `<div class="list-group-item flex-column align-items-start">
                <h5 class="mb-1">${fieldName}</h5>
                <div class="dropdown">
                    <button class="btn btn-primary btn-block dropdown-toggle" type="button" id="dd${fieldName}" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                      ${titleFieldValue}
                    </button>
                    <div class="dropdown-menu" aria-labelledby="dd${fieldName}">
                        ${ subFieldNames.reduce(function (accumulator, subFieldName) {
                            let subFieldValue = fieldValue[subFieldName];
                            return accumulator + `<span class="dropdown-item"><b>${subFieldName}</b>:<br>${subFieldValue}</span>`;
                        }, "")}
                    </div>
                </div>
            </div>`;
}

function updateHoursData($form) {
    let formData = $form.serialize();
    let promise = $.Deferred();

    $.ajax({
        url: "/amo.php",
        data: formData,
        dataType: 'json',
        success: function (result) {
            promise.resolve(result);
        },
        error: function (result) {
            if (result && result.status === 200) {
                promise.resolve(result.responseText);
            }
            else {
                promise.reject(result);
            }
        }
    });

    return promise;
}

function sendNote($form) {
    let formData = $form.serialize();
    let promise = $.Deferred();

    $.ajax({
        url: "/amo.php",
        data: formData,
        dataType: 'json',
        success: function (result) {
            promise.resolve(result);
        },
        error: function (result) {
            if (result && result.status === 200) {
                promise.resolve(result.responseText);
            }
            else {
                promise.reject(result);
            }
        }
    });

    return promise;
}