Vue.component('docs', {
    template: '#docs-template',
    props: ['groups', 'templates'],
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
        }
    },
    computed: {
        cities() {
            return Object.keys(this.templates);
        },
        cityTemplates() {
            return this.currentCity !== false ? this.templates[this.currentCity] : false;
        },
        currentTemplate() {
            if (!this.currentCity || !this.currentTemplateId) {
                return false;
            }

            return this.templates[this.currentCity].reduce((foundTemplate, iteratedTemplate) => {
                if (iteratedTemplate.id == this.currentTemplateId) {
                    return iteratedTemplate;
                }

                return foundTemplate;
            }, false);
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
    props: ['groups'],
    data() {
        return {
            currentGroupCode: false,
        }
    },
    methods: {
        updateCurrentGroup(newGroupCode) {
            this.currentGroupCode = newGroupCode;
        },
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
    props: ['instructors'],
    data() {
        return {
            currentInstructorId: false,
        }
    },
    methods: {
        updateCurrentInstructorId(newInstructorId) {
            this.currentInstructorId = newInstructorId;
        },
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