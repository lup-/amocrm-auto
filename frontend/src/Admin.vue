<template>
    <div id="admin">
        <loader v-if="isLoading"></loader>
        <div class="mb-4" v-else>
            <b-navbar toggleable type="dark" variant="primary">
                <b-navbar-brand href="#">Администратор</b-navbar-brand>
                <b-navbar-toggle target="nav-menu"></b-navbar-toggle>

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

            <component :groups="groups" :instructors="instructorsData" :is="currentTabComponent" :templates="templates"></component>
        </div>
    </div>
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

<style></style>
