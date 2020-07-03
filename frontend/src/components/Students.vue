<template>
    <section class="tab-content px-3 pt-4">
        <div class="tab-pane fade show active">
            <h2 class="text-center">Группы</h2>

            <b-card no-body class="mb-2" v-for="(group, index) in groups" :key="group.name">
                <b-card-header header-tag="header" class="p-1" role="tab">
                    <b-button block v-b-toggle="'group'+index" variant="link" class="d-flex align-items-center">
                        <span class="w-75 mr-4">{{group.name}}</span><span class="btn btn-secondary w-25">{{group.totalPeople}} чел</span>
                    </b-button>
                </b-card-header>
                <b-collapse :id="'group'+index" accordion="groupAccordion" role="tabpanel">
                    <b-card-body>
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item" v-for="student in group.students" :key="student.name">
                                <student-card :student="student" :show-form="false"></student-card>
                            </li>
                        </ul>
                    </b-card-body>
                </b-collapse>
            </b-card>

        </div>
    </section>
</template>

<script>
    import StudentCard from "./StudentCard";

    export default {
        name: "Students",
        props: ['groups', 'templates', 'instructors'],
        components: {StudentCard}
    }
</script>

<style scoped>

</style>