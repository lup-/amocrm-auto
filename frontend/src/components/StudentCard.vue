<template>
    <div class="student-card">
        <b-button v-b-toggle="'student'+showStudent.id" block variant="link" class="text-left pl-0" :class="{'text-danger': showOverdue}">
            {{showStudent.name}}
        </b-button>
        <b-button variant="link" :href="getStudentLink(student)" target="_blank"><b-icon-box-arrow-up-right></b-icon-box-arrow-up-right></b-button>
        <b-collapse :id="'student'+showStudent.id">
            <p class="mb-0">Остаток оплаты: {{showStudent.debt || 0}}</p>
            <p class="mb-0">Оплата ГСМ: {{showStudent.gsmPayment || 0}}</p>
            <p class="mb-0 text-danger" v-if="showOverdue">Просрочка оплаты: {{showStudent.paymentOverdue}} дн.</p>
            <p class="mb-0">Нужное кол-во часов: {{showStudent.neededHours || 0}}</p>
            <p class="mb-0">Откатано часов: {{showStudent.hours || 0}}</p>
            <p class="">Телефон:
                <a :href="'tel:'+showStudent.phone" v-if="showStudent.phone">{{showStudent.phone}}</a>
                <b-button v-else variant="primary" size="sm" @click="getPhone">Загрузить телефон</b-button>
            </p>
            <p class="mb-0">Инструктор: {{showStudent.instructor || 'нет'}}</p>
        </b-collapse>
    </div>
</template>

<script>
    import {loadApiData} from '../modules/api';
    import StudentMixin from "@/mixins/StudentMixin";

    export default {
        name: "StudentCard",
        props: ['student', 'showForm'],
        mixins: [StudentMixin],
        data() {
            return {
                showStudent: this.student,
            }
        },
        watch: {
            student() {
                this.showStudent = this.student;
            }
        },
        methods: {
            async getPhone() {
                let contact = await loadApiData({
                    type: 'getPhone',
                    contactId: this.student.contactId,
                });

                this.showStudent.phone = contact.phone;
                this.$root.$emit('updateStudent', this.showStudent);
            }
        },
        computed: {
            showOverdue() {
                return this.student.paymentOverdue > 10;
            },
        }
    }
</script>

<style scoped>

</style>