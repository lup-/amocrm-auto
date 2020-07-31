<template>
    <b-container fluid class="px-0" id="admin">
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
                        </ul>
                    </div>
                </nav>
                <main class="col-12 col-md-9 ml-sm-auto col-lg-10 p-2">
                    <component :groups="groups" :instructors="instructorsData" :is="currentTabComponent" :templates="templates"></component>
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
                instructorsData: [],
                groupsData: [],
                templates: [],
                currentTabComponent: 'salary',
                menu: [
                    {code: 'salary', title: 'Зарплата по группам', active: true},
                    {code: 'docs', title: 'Документы', active: false},
                    {code: 'instructors', title: 'Зарплата по инструкторам', active: false},
                    {code: 'students', title: 'Группы', active: false},
                ],
            }
        },
        methods: {
            updateActiveMenu(newMenuCode) {
                this.currentTabComponent = newMenuCode;
                this.menu.map(item => {
                    item.active = item.code === newMenuCode;
                });
            },
            async loadInstructorGroupsData() {
                let responseData = await loadApiData({
                    type: 'getAdminData',
                });

                this.instructorsData = responseData.instructors;
                this.groupsData = responseData.groups;
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
</style>
