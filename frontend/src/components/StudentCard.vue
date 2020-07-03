<template>
    <div class="student-card">
        <b-button v-b-toggle="'student'+student.id" block variant="link" class="text-left pl-0" :class="{'text-danger': showOverdue}">{{student.name}}</b-button>
        <b-collapse :id="'student'+student.id">
            <p class="mb-0">Остаток оплаты: {{student.debt || 0}}</p>
            <p class="mb-0">Оплата ГСМ: {{student.gsmPayment || 0}}</p>
            <p class="mb-0 text-danger" v-if="showOverdue">Просрочка оплаты: {{student.paymentOverdue}} дн.</p>
            <p class="mb-0">Нужное кол-во часов: {{student.neededHours || 0}}</p>
            <p class="mb-0">Откатано часов: {{student.hours || 0}}</p>
            <p class="">Телефон: <a :href="'tel:'+student.phone">{{student.phone}}</a></p>
            <p class="mb-0">Инструктор: {{student.instructor || 'нет'}}</p>
            <form class="hoursForm" v-if="showForm">
                <input type="hidden" name="type" value="updateHours">
                <input type="hidden" name="leadId" :value="student.id">
                <div class="form-row align-items-center">
                    <div class="col">
                        <label class="sr-only" :for="'hoursInput-'+student.id">Накатано часов</label>
                        <input type="text" name="hours" class="form-control mb-2" :id="'hoursInput-'+student.id" placeholder="" :value="student.hours">
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-primary mb-2" :data-lead-id="student.id">Сохранить</button>
                    </div>
                </div>
            </form>
        </b-collapse>
    </div>
</template>

<script>
    export default {
        name: "StudentCard",
        props: ['student', 'showForm'],
        computed: {
            showOverdue() {
                return this.student.paymentOverdue > 10;
            }
        }
    }
</script>

<style scoped>

</style>