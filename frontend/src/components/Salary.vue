<template>
    <section class="tab-content px-3 pt-4">
        <div class="tab-pane fade show active">
            <h2 class="text-center">Зарплата</h2>

            <b-dropdown :text="currentGroup ? 'Группа ' + currentGroup.name : 'Выбор группы'" variant="primary" block>
                <b-dropdown-item v-for="group in groups"
                        @click="updateCurrentGroup(group)"
                        :key="group.name"
                >Группа {{group.name}}</b-dropdown-item>
            </b-dropdown>

            <div class="centralContainer mt-4">
                <div v-if="currentGroup" role="tablist">
                    <b-card no-body class="mb-2" v-for="(instructor, index) in currentGroupInstructors" :key="instructor.name">
                        <b-card-header header-tag="header" class="p-1" role="tab">
                            <b-button block v-b-toggle="'heading'+index" variant="link" class="d-flex align-items-center">
                                <span class="w-75 mr-4">{{instructor.name}}</span><span class="btn btn-secondary w-25">{{currentGroupInstructorSalary(instructor)}}</span>
                            </b-button>
                        </b-card-header>
                        <b-collapse :id="'heading'+index" :accordion="'groupAccordion'+groupCode(currentGroup.name)" role="tabpanel">

                            <b-card-body>
                                <b-table
                                        :items="currentGroupInstructorStudents(instructor)"
                                        :fields="tableFields"
                                        caption-top
                                        selectable
                                        select-mode="multi"
                                        responsive="sm"
                                        @row-selected="selected => updateSelected(instructor, selected)"
                                >
                                    <template v-slot:cell(selected)="{ rowSelected, selectRow, unselectRow, index }">
                                        <b-form-checkbox :checked="rowSelected" @change="(value) => toggleRow(value, index, selectRow, unselectRow)"></b-form-checkbox>
                                    </template>
                                    <template v-slot:cell(id)="data">
                                        <b-button variant="link" :href="'https://mailjob.amocrm.ru/leads/detail/'+data.value" target="_blank">{{data.value}}</b-button>
                                    </template>
                                    <template v-slot:cell(hours)="data">
                                        <b-form-input size="sm" v-model="data.value" @change="updateHours(data.item, data.value)"></b-form-input>
                                    </template>
                                </b-table>
                                <b-row>
                                    <b-col>Итого</b-col>
                                    <b-col>{{totalHours(instructor)}}, {{totalSalary(instructor)}}</b-col>
                                </b-row>
                            </b-card-body>

                        </b-collapse>
                    </b-card>
                </div>
                <div v-else>
                    Группа не выбрана
                </div>
            </div>
        </div>
    </section>
</template>

<script>
    import {loadApiData} from "@/modules/api";

    export default {
        name: "Salary",
        props: ['groups', 'templates', 'instructors'],
        data() {
            return {
                currentGroup: false,
                groupNames: [],
                tableFields: [
                    {key: 'selected', label: ''},
                    {key: 'dateFinished', label: 'Дата закрытия'},
                    {key: 'id', label: 'Сделка'},
                    {key: 'name', label: 'Курсант'},
                    {key: 'instructor', label: 'Инструктор'},
                    {key: 'hours', label: 'Часы'},
                    {key: 'salary', label: 'Сумма'},
                ],
                selected: {},
            }
        },
        methods: {
            toggleRow(isSelected, index, selectRow, unselectRow) {
                if (isSelected) {
                    selectRow(index);
                }
                else {
                    unselectRow(index);
                }
            },
            updateSelected(instructor, selected) {
                this.$set(this.selected, instructor.name, selected);
            },
            async updateHours(student, hours) {
                await loadApiData({
                    action: 'updateHours',
                    leadId: student.id,
                    hours
                });

                student.salary = hours * 275;
            },
            getSelectedStudents(instructor) {
                let selectedStudents = this.selected[instructor.name];
                if (!selectedStudents || selectedStudents.length === 0) {
                    selectedStudents = this.currentGroupInstructorStudents(instructor);
                }
                return selectedStudents;
            },
            totalSalary(instructor) {
                return this.getSelectedStudents(instructor).reduce((sum, student) => {
                    return sum+student.salary;
                }, 0);
            },
            totalHours(instructor) {
                return this.getSelectedStudents(instructor).reduce((sum, student) => {
                    return sum+student.hours;
                }, 0);
            },

            updateCurrentGroup(newGroup) {
                this.currentGroup = newGroup;
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
            },
            currentGroupInstructorStudents(instructor) {
                return this.currentGroup.students.filter(student => student.instructor === instructor.name);
            },
            currentGroupInstructorSalary(instructor) {
                let students = this.currentGroupInstructorStudents(instructor);
                return students.reduce( (summ, student) => {
                    summ += student.salary;
                    return summ;
                }, 0);
            }
        },
        computed: {
            currentGroupInstructors() {
                let instructorNames = this.currentGroup.students.map(student => student.instructor);
                let uniqueNames = instructorNames.filter( (instructorName, index) => instructorNames.indexOf(instructorName) === index );
                let groupInstructors = this.instructors.filter( instructor => uniqueNames.indexOf(instructor.name) !== -1 );
                return groupInstructors;
            }
        }
    }
</script>

<style scoped>

</style>