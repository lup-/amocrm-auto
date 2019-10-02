function setupFullCalendar(instructorId) {
    var calendarEl = document.getElementById('calendar');

    var calendar = new FullCalendar.Calendar(calendarEl, {
        plugins: ['dayGrid', 'interaction', 'timeGrid', 'list', 'googleCalendar'],
        header: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,listYear,timeGridWeek,timeGridDay'
        },
        locale: 'ru',
        editable: true,
        evenLimit: true,
        displayEventTime: false,
        eventStartEditable: true,
        eventResizableFromStart: true,
        eventDurationEditable: true,
        eventClick: function (arg) {
            window.open(arg.event.url, '_blank', 'width=1000,height=600');
            arg.jsEvent.preventDefault();
        },
        events: '/calendar.php?action=listEvents&instructorId='+instructorId,
        eventDrop: function (info) {
            var start = info.event.start.toISOString().replace('.000Z', '').replace('T', ' ');
            var end = info.event.end.toISOString().replace('.000Z', '').replace('T', ' ');
            $.ajax({
                url: "/calendar.php?action=update&instructorId=" + instructorId + "&id=" + info.event.id + "&start=" + start + "&end=" + end
            })
        },
    });
    calendar.render();
}