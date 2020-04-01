function zeroPad(number) {
    return number < 10 ? '0'+number : number.toString();
}
function formatDate(date, formatType) {
    let day = zeroPad(date.getDate());
    let month = zeroPad(date.getMonth() + 1);
    let year = date.getFullYear();

    return formatType === 'system'
        ? `${year}-${month}-${day}`
        : `${day}.${month}.${year}`;
}
function dateFromFormat(formattedDate) {
    let parts = formattedDate.split(".");
    return new Date(parts[2], parts[1] - 1, parts[0]);
}

function loadApiData(data, endpointUrl) {
    let promise = $.Deferred();
    if (!endpointUrl) {
        endpointUrl = '/amo.php';
    }

    $.ajax({
        url: endpointUrl,
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
    return "<form class='hoursForm'>\n" +
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

function zeroPad(num) {
    return num < 10 ? '0' + num : num;
}

function getCardHTML(name, leadId, hours, neededHours, debt, phone, eventDate, instructorName, money_remains_summ) {
    let dayNames = ['вс', 'пн', 'вт', 'ср', 'чт', 'пт', 'сб'];
    let dateText = "";

    if (eventDate) {
        let date = new Date(eventDate);
        let dayText = dayNames[date.getDay()];
        dateText = `${dayText}, ${zeroPad(date.getDate())}.${zeroPad(date.getMonth() + 1)}<br> ${zeroPad(date.getHours())}:${zeroPad(date.getMinutes())}`;
    }

    // $('#money_remains_summ').text(money_remains_summ);

    // money_remains_summ += debt;
    // $('#debt').text(debt);
    // console.log('AMO.JS: '+money_remains_summ);

    return `<div class="card">
            <div class="card-header d-flex flex-row justify-content-between" id="heading-${leadId}">
                <a class="mb-0 btn-link flex-fill" href="#" data-toggle="collapse" data-target="#collapse-${leadId}" aria-expanded="true" aria-controls="collapse-${leadId}">
                    ${name}
                </a>
                ${eventDate
                    ? '<span class="date-text">'+dateText+'</span>'
                    : debt
                }
            </div>

            <div id="collapse-${leadId}" class="collapse" aria-labelledby="heading-${leadId}" data-parent="#leadsAccordion">
                <div class="card-body">
                   <p class="mb-0">Остаток оплаты: ${debt}</p>
                   <p class="mb-0">Нужное кол-во часов: ${neededHours}</p>
                   <p class="mb-0">Откатано часов: ${hours}</p>
                   <p class="">Телефон: <a href="tel:${phone}">${phone}</a></p>
                   <p class="mb-0">Инструктор: ${instructorName}</p>
                   ${getFromHTML(leadId, hours)}
                </div>
            </div>
        </div>`;
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

function getTimeframeHTML(timeframe, studentName) {
    let disabledClass = studentName ? '' : 'disabled';
    if (!studentName) {
        studentName = '-';
    }

    return `<li class="list-group-item d-flex flex-row-reverse justify-content-between align-items-center ${disabledClass}">
    ${studentName}
    <span class="badge badge-primary badge-pill mr-4">${timeframe}</span>
  </li>`;
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

function addStudentEvent($form) {
    let formData = $form.serialize();
    let promise = $.Deferred();

    $.ajax({
        url: "/calendar.php",
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

function loadCalendarEvents(instructorId, date) {
    return loadApiData({
        action: 'list',
        instructorId: instructorId,
        date: formatDate(date, 'system')
    }, '/calendar.php')
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

function drawUserMenu() {
    let userId = getParameterByName('id');
    let isUserActive = location.href.indexOf('user.html') !== -1;
    let isVideoActive = location.href.indexOf('video.html') !== -1;
    let isTicketsActive = location.href.indexOf('tickets.html') !== -1;

    let menuHTML = `<nav class="navbar navbar-dark bg-primary">
        <span class="navbar-brand" id="title">Кабинет ученика</span>
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarSupportedContent1"
                aria-controls="navbarSupportedContent1" aria-expanded="false" aria-label="Показать меню"><span class="navbar-toggler-icon"></span></button>
    
        <div class="collapse navbar-collapse" id="navbarSupportedContent1">
            <ul class="navbar-nav mr-auto nav nav-tabs" role="tablist">
                <li class="nav-item">
                    ${isUserActive
                        ? '<a class="nav-link active show" aria-selected="true">Мои данные</a>'
                        : '<a class="nav-link" href="user.html?id='+userId+'">Мои данные</a>'
                    }
                </li>
                <li class="nav-item">
                    ${isVideoActive
                        ? '<a class="nav-link active show" aria-selected="true">Уроки</a>'
                        : '<a class="nav-link" href="video.html?id='+userId+'">Уроки</a>'
                    }
                </li>
                <li class="nav-item">
                    ${isTicketsActive
                        ? '<a class="nav-link active show" aria-selected="true">Билеты</a>'
                        : '<a class="nav-link" href="tickets.html?id='+userId+'">Билеты</a>'
                    }
                </li>
            </ul>
        </div>
    </nav>`;

    $('body').prepend(menuHTML);
}