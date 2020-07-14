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
                                <ul class="list-group list-group-flush">
                                    <li class="list-group-item" v-for="student in currentGroupInstructorStudents(instructor)" :key="student.name">{{student.name}}</li>
                                </ul>
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
    export default {
        name: "Salary",
        props: ['groups', 'templates', 'instructors'],
        data() {
            return {
                currentGroup: false,
                groupNames: []
            }
        },
        methods: {
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