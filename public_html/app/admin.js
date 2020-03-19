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
    computed: {
        // return fetch('https://batalichev.pro/auto/getStudents');
        return 'test list';
    }
});

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
            { code: 'students', title: 'Обучающиеся и их инструкторы', active: false},
        ],
    },
    methods: {
        updateActiveMenu(newMenuCode) {
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