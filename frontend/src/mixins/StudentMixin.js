export default {
    methods: {
        getStudentLink(student) {
            return `https://mailjob.amocrm.ru/leads/detail/${student.id}`;
        }
    }
}