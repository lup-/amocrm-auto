<template>
    <section class="tab-content px-3 pt-4">
        <div class="tab-pane fade show active">
            <h2 class="text-center">Инструкторы</h2>

            <b-dropdown :text="currentInstructor ? currentInstructor.name : 'Выбор инструктора'" variant="primary" block>
                <b-dropdown-item v-for="instructor in instructors"
                        @click="updateCurrentInstructorId(instructor.id)"
                        :key="instructor.id"
                >{{instructor.name}}</b-dropdown-item>
            </b-dropdown>

             <div class="centralContainer mt-4">
                 <div v-if="currentInstructor" role="tablist">
                     <div>
                         Всего ЗП: {{currentInstructorSalary}}
                     </div>
                     <b-card no-body class="mb-2" v-for="group in instructorGroups(currentInstructor)" :key="group.name">
                         <b-card-header header-tag="header" class="p-1" role="tab">
                             <b-button block v-b-toggle="'collapse'+groupCode(group.name)" variant="link" class="d-flex align-items-center">
                                 <span class="w-75 mr-4">Группа {{group.name}}</span><span class="btn btn-secondary w-25">{{totalSalary(group)}}</span>
                             </b-button>
                         </b-card-header>
                         <b-collapse :id="'collapse'+groupCode(group.name)" :accordion="'instructorAccordion'+currentInstructor.id" role="tabpanel">
                             <b-card-body>
                                 <b-table
                                         :items="instructorStudentsInGroup(group)"
                                         :fields="tableFields"
                                         caption-top
                                         selectable
                                         select-mode="multi"
                                         responsive="sm"
                                         @row-selected="selected => updateSelected(group, selected)"
                                 >
                                     <template v-slot:cell(selected)="{ rowSelected }">
                                         <b-form-checkbox :value="rowSelected"></b-form-checkbox>
                                     </template>
                                     <template v-slot:cell(id)="data">
                                         <b-button variant="link" :href="'https://mailjob.amocrm.ru/leads/detail/'+data.id" target="_blank">{{data.id}}</b-button>
                                     </template>
                                     <template v-slot:cell(hours)="data">
                                         <b-form-input size="sm" v-model="data.value" @change="updateHours(data.item, data.value)"></b-form-input>
                                     </template>
                                 </b-table>
                                 <b-row>
                                     <b-col>Итого</b-col>
                                     <b-col>{{totalHours(group)}}, {{totalSalary(group)}}</b-col>
                                 </b-row>
                             </b-card-body>
                         </b-collapse>
                     </b-card>
                 </div>
                <div v-else>
                    Инструктор не выбран
                </div>
            </div>

        </div>
    </section>
</template>

<script>
    import {loadApiData} from "@/modules/api";

    export default {
        name: "Instructors",
        props: ['groups', 'templates', 'instructors'],
        data() {
            return {
                currentInstructorId: false,
                tableFields: [
                    {key: 'selected', label: ''},
                    {key: 'dateFinished', label: 'Дата закрытия'},
                    {key: 'id', label: 'Сделка'},
                    {key: 'name', label: 'Курсант'},
                    {key: 'group', label: '№ группы'},
                    {key: 'hours', label: 'Часы'},
                    {key: 'salary', label: 'Сумма'},
                ],
                groupNames: [],
                selected: {},
            }
        },
        methods: {
            updateSelected(group, selected) {
                this.$set(this.selected, group.name, selected);
            },
            instructorStudentsInGroup(group) {
                return group.students.filter( student => student.instructor === this.currentInstructor.name );
            },
            updateCurrentInstructorId(newInstructorId) {
                this.currentInstructorId = newInstructorId;
            },
            async updateHours(student, hours) {
                await loadApiData({
                    action: 'updateHours',
                    leadId: student.id,
                    hours
                });

                student.salary = hours * 275;
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
            instructorGroups(instructor) {
                return this.groups.filter( group => {
                    let instructorStudents = group.students.filter(student => student.instructor === instructor.name);
                    return instructorStudents.length > 0;
                });
            },
            totalSalary(group) {
                let selected = this.selected[group.name] || this.instructorStudentsInGroup(group);
                return selected.reduce((sum, student) => {
                    return sum+student.salary;
                }, 0);
            },
            totalHours(group) {
                let selected = this.selected[group.name] || this.instructorStudentsInGroup(group);
                return selected.reduce((sum, student) => {
                    return sum+student.hours;
                }, 0);
            },
            instructorGroupSalary(instructor, group) {
                let instructorStudentsInGroup = instructor.students.filter(student => student.group === group.name);
                return instructorStudentsInGroup.reduce( (summ, student) => {
                    summ += student.salary;
                    return summ;
                }, 0);
            }
        },
        computed: {
            currentInstructor() {
                if (!this.currentInstructorId ) {
                    return false;
                }

                return this.instructors.find(instructor => instructor.id === this.currentInstructorId) || false;
            },
            currentInstructorSalary() {
                if (!this.currentInstructorId ) {
                    return false;
                }

                return this.currentInstructor.students.reduce( (summ, student) => {
                    summ += student.salary;
                    return summ;
                }, 0);
            },
        }

    }
</script>

<style scoped>

</style>