document.addEventListener('DOMContentLoaded', function () {
  var calendarEl = document.getElementById('calendar');

  var calendar = new FullCalendar.Calendar(calendarEl, {
    plugins: ['dayGrid', 'interaction', 'timeGrid', 'list', 'googleCalendar'],
    header: {
      left: 'prev,next today',
      center: 'title',
      right: 'dayGridMonth,listYear,timeGridWeek,timeGridDay'
    },
    editable: true,
    evenLimit: true,
    displayEventTime: false,
    eventStartEditable: true,
    eventResizableFromStart: true,
    eventDurationEditable: true,
    eventClick: function (arg) {
      // opens events in a popup window
      window.open(arg.event.url, '_blank', 'width=1000,height=600');
      // prevents current tab from navigating
      arg.jsEvent.preventDefault();
    },
    // eventDragStart: function (info) {

    //   var start = info.event.start.toISOString().replace('.000Z', '').replace('T', ' ');
    //   console.log(start);
    //   var end = info.event.end.toISOString().replace('.000Z', '').replace('T', ' ');
    //   $.ajax({
    //     url: "http://amo-auto.humanistic.tech/calendar.php?action=update&instructorId=920531&id=" + info.event.id + "&start=" + start + "&end=" + end
    //   })
    // },
    events: 'http://amo-auto.humanistic.tech/calendar.php?action=listEvents&instructorId=920531',
    eventDrop: function (info) {
      var start = info.event.start.toISOString().replace('.000Z', '').replace('T', ' ');
      var end = info.event.end.toISOString().replace('.000Z', '').replace('T', ' ');
      $.ajax({
        url: "http://amo-auto.humanistic.tech/calendar.php?action=update&instructorId=920531&id=" + info.event.id + "&start=" + start + "&end=" + end
      })
    },
  });
  calendar.render();
});