function loadInstructors() {
    let promise = $.Deferred();

    $.ajax({
        url: "/amo.php",
        data: {type: 'instructors'},
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

function loadHours(instructorId) {
    let promise = $.Deferred();

    $.ajax({
        url: "/amo.php",
        data: {
            type: 'getHours',
            instructorId: instructorId
        },
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

function getCardHTML(name, leadId, hours) {
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
                    getFromHTML(leadId, hours) +
        "        </div>\n" +
        "    </div>\n" +
        "</div>";
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