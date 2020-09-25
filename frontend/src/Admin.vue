<template>
    <b-container fluid class="px-0" id="admin">
        <b-alert variant="success" dismissible show class="top-alert" @dismissed="successMessage = false" v-if="successMessage">{{successMessage}}</b-alert>
        <b-alert variant="danger" dismissible show class="top-alert" @dismissed="errorMessage = false" v-if="errorMessage">{{errorMessage}}</b-alert>

        <loader v-if="isLoading"></loader>
        <div v-else>
            <b-navbar toggleable type="dark" variant="primary" class="sticky-navbar">
                <b-navbar-brand href="#">Администратор</b-navbar-brand>
                <b-navbar-toggle target="nav-menu" class="d-block d-md-none"></b-navbar-toggle>

                <b-collapse id="nav-menu" is-nav>
                    <b-navbar-nav>
                        <b-nav-item
                                v-for="item in menu"
                                :class="{show: item.active, active: item.active}"
                                :key="item.code"

                                @click="updateActiveMenu(item.code)"
                        >
                            {{item.title}}
                        </b-nav-item>

                    </b-navbar-nav>
                </b-collapse>
            </b-navbar>

            <div class="row mx-0 md-4">
                <nav class="d-none col-md-3 col-lg-2 d-md-block bg-light sidebar">
                    <div class="sidebar-sticky">
                        <ul class="nav flex-column">
                            <li class="nav-item" v-for="item in menu" :key="item.code+'-side'">
                                <a class="nav-link" :class="{active: item.active}" href="#" @click="updateActiveMenu(item.code)">
                                    {{item.title}}
                                </a>
                            </li>
                            <b-dropdown-divider></b-dropdown-divider>
                        </ul>

                        <b-button variant="primary" block @click="refreshData">Обновить данные</b-button>
                        <b-button variant="primary" block class="mt-2" @click="syncInstructors">Обновить инструкторов</b-button>
                    </div>
                </nav>
                <main class="col-12 col-md-9 ml-sm-auto col-lg-10 p-2">
                    <component
                        :groups="completeOrActiveGroups"
                        :instructors="instructorsData"
                        :is="currentTabComponent"
                        :templates="templates"
                        @newDoc="addNewDocument"
                        @newGroupDoc="addNewGroupDocument"
                    ></component>
                </main>
            </div>
        </div>
    </b-container>
</template>

<script>
    import Loader from "./components/Loader";
    import Docs from "./components/Docs";
    import Salary from "./components/Salary";
    import Instructors from "./components/Instructors";
    import Students from "./components/Students";
    import {loadApiData} from './modules/api';

    function clone(obj) {
        return JSON.parse( JSON.stringify(obj) );
    }

    export default {
        name: 'Admin',
        components: {
            Loader,
            Docs,
            Salary,
            Instructors,
            Students
        },
        data() {
            return {
                isLoading: true,
                successMessage: false,
                errorMessage: false,
                instructorsData: [],
                groupsData: [],
                completeGroupsData: [],
                templates: [],
                currentTabComponent: 'docs',
                menu: [
                    {code: 'salary', title: 'Зарплата по группам', active: false},
                    {code: 'docs', title: 'Документы', active: true},
                    {code: 'instructors', title: 'Зарплата по инструкторам', active: false},
                    {code: 'students', title: 'Группы', active: false},
                ],
            }
        },
        methods: {
            addNewDocument(targetGroup, studentId, newDoc) {
                let changedGroup = clone(targetGroup);
                let studentIndex = changedGroup.students.findIndex(student => student.id === studentId);
                let student = changedGroup.students[studentIndex];

                if (!student.docs) {
                    student.docs = [];
                }

                student.docs.push(newDoc);
                changedGroup.students[studentIndex] = student;
                this.$set( this.groupsData, changedGroup.name, changedGroup);
            },
            addNewGroupDocument(targetGroup, newDoc) {
                let changedGroup = clone(targetGroup);
                if (!changedGroup.docs) {
                    changedGroup.docs = [];
                }

                changedGroup.docs.unshift(newDoc);
                this.$set( this.groupsData, changedGroup.name, changedGroup);
            },
            updateActiveMenu(newMenuCode) {
                this.currentTabComponent = newMenuCode;
                this.menu.map(item => {
                    item.active = item.code === newMenuCode;
                });
            },
            async refreshData() {
                this.isLoading = true;
                await Promise.all([
                    this.loadInstructorGroupsData(true),
                    this.loadDocumentTemplatesData()
                ]);
                this.isLoading = false;
            },
            async syncInstructors() {
                let responseData = await loadApiData({type: 'syncInstructors'});
                this.successMessage = `Успешно обновлено. Добавлено календарей: ${responseData.added.length}. Удалено календарей: ${responseData.removed.length}.`;
            },
            async loadInstructorGroupsData(refresh = false) {
                let params = {
                    type: 'getAdminData',
                };
                if (refresh) {
                    params['loadFromAMO'] = 1;
                }
                let responseData = await loadApiData(params);

                this.instructorsData = responseData.instructors;
                this.groupsData = responseData.groups;
                this.completeGroupsData = responseData.completeGroups;
            },
            async loadDocumentTemplatesData() {
                let responseData = await loadApiData({
                    action: 'list',
                }, '/files.php');

                this.templates = responseData;
            }
        },
        async beforeMount() {
            await Promise.all([
                this.loadInstructorGroupsData(),
                this.loadDocumentTemplatesData()
            ]);
            this.isLoading = false;
        },
        computed: {
            groups() {
                return Object.values(this.groupsData);
            },
            completeGroups() {
                return Object.values(this.completeGroupsData);
            },
            completeOrActiveGroups() {
                let componentNeedsCompleteGroups = this.currentTabComponent === 'salary' || this.currentTabComponent === 'instructors';
                return componentNeedsCompleteGroups
                    ? this.completeGroups
                    : this.groups;
            }
        }
    }
</script>

<style>
    .sidebar {
        position: fixed!important;
        top: 0;
        bottom: 0;
        left: 0;
        z-index: 50;
        padding: 56px 0 0;
        box-shadow: inset -1px 0 0 rgba(0, 0, 0, .1);
    }

    .sidebar-sticky {
        position: sticky!important;
        top: 0;
        height: calc(100vh - 48px);
        padding-top: .5rem;
        overflow-x: hidden;
        overflow-y: auto;
    }

    .sticky-navbar {
        position: sticky!important;
        top: 0;
        z-index: 100;
    }

    .top-alert {
        position: absolute!important;
        z-index: 1000;
        width: 100%;
    }
</style>
