function clone(object) {
    return JSON.parse(JSON.stringify(object));
}

Vue.component('docs', {
    template: '#docs-template',
    props: ['groups', 'templates', 'instructors'],
    data() {
        return {
            currentCity: false,
            currentTemplateId: false,
            currentGroupCode: false,
        }
    },
    methods: {
        updateCurrentCity(newCity) {
            this.currentCity = newCity;
        },
        updateCurrentTemplateId(newTemplateId) {
            this.currentTemplateId = newTemplateId;
        },
        updateCurrentGroup(newGroupCode) {
            this.currentGroupCode = newGroupCode;
        },
        downloadSelectedDocument(studentId) {
            window.location.href = `/files.php?action=makedoc&templateId=${this.currentTemplateId}&leadId=${studentId}`;
        },
        downloadSelectedGroupDocument(group) {
            window.location.href = `/files.php?action=makegroupdoc&templateId=${this.currentTemplateId}&group=${group.name}`;
        }
    },
    computed: {
        cities() {
            return Object.keys(this.templates);
        },
        cityTemplates() {
            if (this.currentCity === false) {
                return false;
            }

            let templates = [];
            Object.keys(this.templates[this.currentCity]).forEach((type) => {
                this.templates[this.currentCity][type].forEach((template) => {
                    let typedTemplate = clone(template);
                    typedTemplate.type = type;

                    templates.push(typedTemplate);
                });
            });

            return templates;
        },
        currentTemplate() {
            if (!this.currentCity || !this.currentTemplateId) {
                return false;
            }

            return this.cityTemplates.reduce((foundTemplate, iteratedTemplate) => {
                if (iteratedTemplate.id == this.currentTemplateId) {
                    return iteratedTemplate;
                }

                return foundTemplate;
            }, false);
        },
        isCurrentTemplatePersonal() {
            return this.currentTemplate && this.currentTemplate.type === 'personal';
        },
        currentGroup() {
            return this.currentGroupCode !== false
                ? this.groups[this.currentGroupCode]
                : false;
        }
    }
});

Vue.component('salary', {
    template: '#salary-template',
    props: ['groups', 'templates', 'instructors'],
    data() {
        return {
            currentGroupCode: false,
            groupNames: []
        }
    },
    methods: {
        updateCurrentGroup(newGroupCode) {
            this.currentGroupCode = newGroupCode;
        },
        groupCode(groupName) {
            let groupIndex = this.groupNames.indexOf(groupName);

            if (groupIndex !== -1) {
                return groupIndex;
            }
            else {
                this.groupNames.push(groupName);
                return this.groupNames.indexOf(groupName);
            }
        }
    },
    computed: {
        currentGroup() {
            return this.currentGroupCode !== false
                ? this.groups[this.currentGroupCode]
                : false;
        }
    }
});

Vue.component('instructors', {
    template: '#instructors-template',
    props: ['groups', 'templates', 'instructors'],
    data() {
        return {
            currentInstructorId: false,
            groupNames: []
        }
    },
    methods: {
        updateCurrentInstructorId(newInstructorId) {
            this.currentInstructorId = newInstructorId;
        },
        groupCode(groupName) {
            let groupIndex = this.groupNames.indexOf(groupName);

            if (groupIndex !== -1) {
                return groupIndex;
            }
            else {
                this.groupNames.push(groupName);
                return this.groupNames.indexOf(groupName);
            }
        }
    },
    computed: {
        currentInstructor() {
            if (!this.currentInstructorId ) {
                return false;
            }

            return this.instructors.reduce((foundInstructor, iteratedInstructor) => {
                if (iteratedInstructor.id == this.currentInstructorId) {
                    return iteratedInstructor;
                }

                return foundInstructor;
            }, false);
        },
        currentInstructorSalary() {
            if (!this.currentInstructorId ) {
                return false;
            }

            return Object.keys(this.currentInstructor.groups).reduce( (summ, code) => {
                let group = this.currentInstructor.groups[code];
                summ += group.salary;
                return summ;
            }, 0);
        }
    }
});

Vue.component('students', {
    template: '#students-template',
    props: ['groups', 'templates', 'instructors'],
    data: function () {
        return {
            mass: 'Mass text'
        }
    },
    computed: {
        students_list() {
            // this.mass += '<h2>++++++++++++++++++</h2>';
//             var names = '';
//             var instructorsCount = Object.keys(this.instructors);
//             var names_list = [];
//             var st_count = 0;

//             for (instr in instructorsCount){
//                 var students = this.instructors[instr].students;
//                 var studentsKeys = Object.keys(students);                

//                 for (i in studentsKeys){
//                     var group = this.instructors[instr].students[studentsKeys[i]];
//                     var instructorName = this.instructors[instr].name;
//                     var count = group.length;
//                     console.log(count, instructorName);

//                     for (let z=0; z<count; z++){
//                         console.log();
//                         var id = this.instructors[instr].students[studentsKeys[i]][z].id;
//                         var name = id+' '+this.instructors[instr].students[studentsKeys[i]][z].name;
                        
// //                         if(!names_list.includes(name)){
//                         if(true){
//                             names += name+' — '+instructorName+'<br>';
//                             names_list.push(name);
                            
//                             console.log();
//                         }
                        
//                     }
//                 }
//             }
            
//             console.log(this.groups)
//             names_list = []
            // return names;
            // return '2nd mass';

            let calendar = false;
        function updateCalendar() {
            calendar.render();
        }
        function sortByGroups(leadsData) {
            let sortedLeads = {};

            Object.keys(leadsData).forEach(function (leadId) {
                let leadData = leadsData[leadId];
                let groupName = leadData['group'];

                if (typeof (sortedLeads[groupName]) === 'undefined') {
                    sortedLeads[groupName] = {};
                }

                sortedLeads[groupName][leadId] = leadData;
            });

            return sortedLeads;
        }
        function loadAndShowInstructorData(instructorId) {
            loadHours(instructorId)
                .then(function (responseData) {
                    let instructorName = responseData.instructor;
                    let unsortedLeadsData = responseData.leads;
                    let groupsData = responseData.groups;
                    let totalLeadsCount = Object.keys(unsortedLeadsData).length;
                    let sortedLeads = sortByGroups(unsortedLeadsData);
                    let groupNames = Object.keys(groupsData);
                    let totalSalary = Object.keys(groupsData).reduce(function (accumulator, groupName) {
                        let group = groupsData[groupName];
                        return accumulator + group.salary;
                    }, 0);

                    // $('#leadsAccordion').html("")
                    // $('#instructorName').html(instructorName+' добавлен');
                    // $('#totalCount').html(totalLeadsCount);
                    // $('#totalSalary').html(totalSalary);
                    

                    groupNames.forEach(function (groupName) {
                        let groupLeadsData = sortedLeads[groupName];
                        let leadsId = Object.keys(groupLeadsData);
                        let groupData = groupsData[groupName];
                        let money_remains_summ = 0;

                        leadsId.forEach(function (leadId) {
                            let leadData = groupLeadsData[leadId];
                            money_remains_summ += Number(leadData.debt || 0);
                            
                        })

                        this.mass += `<div class='h6 my-3'>
                            <div class='title-wrapper'>
                              <div class="dropdown">
                                <div class="d-flex justify-content-between align-items-end">
                                    <button class="btn btn-primary dropdown-toggle" type="button" id="dd${groupName}" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                      Группа ${groupName}
                                    </button>
                                    <div class="dropdown-menu" aria-labelledby="dd${groupName}">
                                      <span class='dropdown-item'>ЗП: ${groupData.salary || "-"}</span>
                                      <span class='dropdown-item'>Человек: ${groupData.people || "-"}</span>
                                      <span class='dropdown-item'>Начало: ${(groupData.start || "-")}</span>
                                      <span class='dropdown-item'>Конец: ${(groupData.end || "-")}</span>
                                      <span class='dropdown-item'>Дата экзамена:  ${(groupData.exam || "-")}</span>
                                    </div>
                                    <span>С группы: ${money_remains_summ}</span>
                                </div>
                              </div>
                            </div>`;

                        leadsId.forEach(function (leadId) {
                            let leadData = groupLeadsData[leadId];
                            // money_remains_summ += Number(leadData.debt);
                            // console.log(money_remains_summ);
                            this.mass +=
                                getCardHTML(
                                    leadData.contact,
                                    leadId,
                                    leadData.hours || "",
                                    leadData.neededHours || "",
                                    leadData.debt || 0,
                                    leadData.phone,
                                    leadData.schedule || false,
                                    instructorName
                                );
                            
                        })
                    });


                });
        }
        function initStudentTab(instructorId) {
            let today = new Date;
            $('[name="instructorId"]').val(instructorId);
            $('[name="date"]').val(formatDate(today, 'system'));
            updateTimeframeInput(instructorId, today);

            $(document).on('change', '#date', function (event) {
                let eventDate = new Date($(this).val());
                updateTimeframeInput(instructorId, eventDate);
            });

            $(document).on('change', '#time', function (event) {
                if ($(this).val() === "-1") {
                    $('#time-detail').show().attr('name', 'time');
                    $('#time').attr('name', false);
                }
                else {
                    $('#time-detail').hide().attr('name', false);
                    $('#time').attr('name', 'time');
                }
            });

            $(document).on('submit', 'form.studentForm', function (event) {
                event.preventDefault();

                let $form = $(this);
                let $button = $form.find('button');
                $button
                    .removeClass('btn-danger btn-primary')
                    .addClass('btn-primary')
                    .attr('disabled', 'disabled')
                    .text('Запись ...');

                addStudentEvent($form)
                    .then(function () {
                        $form[0].reset();
                        $button.attr('disabled', false).text('Записать');

                        let currentScheduleDate = dateFromFormat($('.schedule-date').text());
                        loadAndShowSchedule(instructorId, currentScheduleDate);
                        loadAndShowInstructorData(instructorId);
                    })
                    .catch(function () {
                        $button
                            .attr('disabled', false)
                            .removeClass('btn-danger btn-primary')
                            .addClass('btn-danger')
                            .text('Записать повторно');
                    });

            });
        }
        function loadAndShowSchedule(instructorId, date) {
            return loadCalendarEvents(instructorId, date)
                .then(function (eventsResponse) {
                    let timeframes = Object.keys(eventsResponse);
                    let timeframesHTML = timeframes.map(function (timeframe) {
                        let events = eventsResponse[timeframe];
                        let studentName = events.length > 0
                            ? events[0].text
                            : false;

                        return getTimeframeHTML(timeframe, studentName);
                    }).join("\n");

                    let scheduleHTML = `<ul class="list-group">${timeframesHTML}</ul>`;
                    $('.schedule-list').html(scheduleHTML);
                });
        }
        function getDateTimeframes(instructorId, date) {
            return loadCalendarEvents(instructorId, date)
                .then(function (eventsResponse) {
                    let timeframes = Object.keys(eventsResponse);
                    let availtimeframes = timeframes.reduce(function (timeframeAccumulator, timeframe) {
                        if (eventsResponse[timeframe].length === 0) {
                            timeframeAccumulator.push(timeframe);
                        }
                        return timeframeAccumulator;
                    }, []);

                    return availtimeframes;
                });
        }
        function updateTimeframeInput(instructorId, eventDate) {
            return getDateTimeframes(instructorId, eventDate)
                .then(function (timeframes) {
                    let optionsHTML = timeframes.map(function (timeframe) {
                        return `<option value="${timeframe}:00">${timeframe}</option>`;
                    }).join('\n');
                    optionsHTML += "<option value=\"-1\">Другое</option>";

                    $('#time').html(optionsHTML);
                });
        }
        function initGroupsTab(instructorId) {
            // loadAndShowInstructorData(instructorId);
            let instructors = [788903, 920539, 920525, 920527, 1068181, 920533, 920531, 920537, 920563, 1074817, 1074819, 1098473, 1104055, 1113415, 1113417, 920541, 1064781, 1115851, 1118661]

            for(instructor in instructors){
                console.log(instructors[instructor])
                loadAndShowInstructorData(instructors[instructor]);
            }

            $(document).on('click', '.btn-calendar', function (event) {
                event.preventDefault();
                let name = $(this).data('name');
                $('#studentName').val(name);
                $('#student-tab').tab('show');
            });

            $(document).on('submit', 'form.hoursForm', function (event) {
                event.preventDefault();

                let $form = $(this);
                let $button = $form.find('button');
                $button
                    .removeClass('btn-danger btn-primary')
                    .addClass('btn-primary')
                    .attr('disabled', 'disabled')
                    .text('Сохранение ...');

                updateHoursData($form)
                    .then(function () {
                        $button.attr('disabled', false).text('Сохранить');
                    })
                    .catch(function () {
                        $button
                            .attr('disabled', false)
                            .removeClass('btn-danger btn-primary')
                            .addClass('btn-danger')
                            .text('Сохранить повторно');
                    });

            });
        }

        function initScheduleTab(instructorId) {
            calendar = setupFullCalendar(instructorId);
            updateCalendar();

            $('a[data-toggle="tab"]').on('shown.bs.tab', function () {
                updateCalendar();
            });
        }

        $(function () {
            let instructorId = getParameterByName('id');
            initGroupsTab(instructorId);
            // initStudentTab(instructorId);
            // initScheduleTab(instructorId);
        });
            }
}});


new Vue({
    el: '#admin',
    data: {
        isLoading: true,
        instructorsData: [],
        templates: [],
        currentTabComponent: 'salary',
        menu: [
            { code: 'salary', title: 'Зарплата по группам', active: true },
            { code: 'docs', title: 'Документы', active: false },
            { code: 'instructors', title: 'Зарплата по инструкторам', active: false},
            { code: 'students', title: 'Группы', active: false},
        ],
    },
    methods: {
        updateActiveMenu(newMenuCode) {
            // if(newMenuCode === 'students'){
            //     location.href = 'new_instructor.html?id=788903';
            // }
            this.currentTabComponent = newMenuCode;
            this.menu.map(item => {
                item.active = item.code === newMenuCode;
            });
        },
        loadInstructorGroupsData() {
            return loadApiData({
                type: 'getAllInstructorsData',
            }).then( (responseData) => {
                this.instructorsData = responseData.instructors;
            });
        },
        loadDocumentTemplatesData() {
            return loadApiData({
                action: 'list',
            }, '/files.php').then( (responseData) => {
                this.templates = responseData;
            });
        }
    },
    beforeMount(){
        Promise.all([
            this.loadInstructorGroupsData(),
            this.loadDocumentTemplatesData()
        ]).then(() => {
            this.isLoading = false;
        });
    },
    computed: {
        groups() {
            let groupsAsObject = this.instructorsData.reduce( (groupsData, instructor) => {
                Object.keys(instructor.groups).map((groupCode) => {
                    let currentInstructorGroup = instructor.groups[groupCode];
                    let students = instructor.students[groupCode];
                    let totalGroupData = typeof (groupsData[groupCode]) !== 'undefined'
                        ? groupsData[groupCode]
                        : {
                            name: currentInstructorGroup.name,
                            start: currentInstructorGroup.start,
                            end: currentInstructorGroup.end,
                            exam: currentInstructorGroup.exam,
                            totalPeople: 0,
                            instructors: [],
                            students: students,
                        };

                    totalGroupData.people += currentInstructorGroup.people;
                    totalGroupData.instructors.push({
                        'id': instructor.id,
                        'name': instructor.name,
                        'people': currentInstructorGroup.people,
                        'salary': currentInstructorGroup.salary,
                        'students': students,
                    });

                    students.forEach(student => {
                        totalGroupData.students.push(student);
                    });

                    groupsData[groupCode] = totalGroupData;
                });

                return groupsData;
            }, {});

            return groupsAsObject;
        }
    }
});