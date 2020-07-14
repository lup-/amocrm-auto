<template>
    <section class="tab-content px-3 pt-4">
        <div class="tab-pane fade show active">
            <h2 class="text-center">Документы</h2>

            <b-dropdown :text="currentCity ? currentCity : 'Выбор города'" variant="primary" block class="mb-2">
                <b-dropdown-item v-for="city in cities"
                        @click="updateCurrentCity(city)"
                        :key="city"
                >{{city}}</b-dropdown-item>
            </b-dropdown>

            <b-dropdown v-if="currentCity" :text="currentGroup ? 'Группа ' + currentGroup.name : 'Выбор группы'" variant="primary" block class="mb-2">
                <b-dropdown-item v-for="group in groups"
                        @click="updateCurrentGroup(group)"
                        :key="group.name"
                >{{group.name}}</b-dropdown-item>
            </b-dropdown>

            <b-dropdown v-if="currentGroup" :text="currentTemplate ? currentTemplate.title : 'Выбор шаблона'" variant="primary" block class="mb-2">
                <b-dropdown-text>На каждого студента</b-dropdown-text>
                <b-dropdown-item v-for="template in this.templates[this.currentCity]['personal']"
                        @click="updateCurrentTemplateId(template.id)"
                        :key="template.id"
                >
                    {{template.title}}
                </b-dropdown-item>
                <b-dropdown-divider></b-dropdown-divider>
                <b-dropdown-text>На всю группу</b-dropdown-text>
                <b-dropdown-item v-for="template in this.templates[this.currentCity]['group']"
                        @click="updateCurrentTemplateId(template.id)"
                        :key="template.id"
                >
                    {{template.title}}
                </b-dropdown-item>
            </b-dropdown>

            <ul class="list-group mt-4" v-if="currentGroup && currentTemplate && isCurrentTemplatePersonal">
                <li class="list-group-item d-flex align-items-center" v-for="student in currentGroup.students" :key="student.id">
                    <span class="w-75 mr-4 flex-fill">{{student.name}}</span>
                    <button class="btn btn-primary" @click="downloadSelectedDocument(student.id)">
                        <b-icon-download></b-icon-download>
                    </button>
                </li>
            </ul>

            <button class="btn btn-primary btn-block mt-4" @click="downloadSelectedGroupDocument(currentGroup)" v-else-if="currentGroup && currentTemplate">
                <b-icon-download></b-icon-download>
                Скачать документ для группы
            </button>
        </div>
    </section>
</template>

<script>
    import {clone} from "../modules/objectsArrays";

    export default {
        name: "Docs",
        props: ['groups', 'templates', 'instructors'],
        data() {
            return {
                currentCity: 'Железнодорожный',
                currentTemplateId: false,
                currentGroup: false,
            }
        },
        methods: {
            updateCurrentCity(newCity) {
                this.currentCity = newCity;
            },
            updateCurrentTemplateId(newTemplateId) {
                this.currentTemplateId = newTemplateId;
            },
            updateCurrentGroup(newGroup) {
                this.currentGroup = newGroup;
            },
            downloadSelectedDocument(studentId) {
                window.location.href = `/files.php?action=makedoc&templateId=${this.currentTemplateId}&leadId=${studentId}`;
            },
            downloadSelectedGroupDocument(group) {
                window.location.href = `/files.php?action=makegroupdoc&templateId=${this.currentTemplateId}&group=${group.name}`;
            }
        },
        computed: {
            cities() {
                return Object.keys(this.templates);
            },
            cityTemplates() {
                if (this.currentCity === false) {
                    return false;
                }

                let templates = [];
                Object.keys(this.templates[this.currentCity]).forEach((type) => {
                    this.templates[this.currentCity][type].forEach((template) => {
                        let typedTemplate = clone(template);
                        typedTemplate.type = type;

                        templates.push(typedTemplate);
                    });
                });

                return templates;
            },
            currentTemplate() {
                if (!this.currentCity || !this.currentTemplateId) {
                    return false;
                }

                return this.cityTemplates.reduce((foundTemplate, iteratedTemplate) => {
                    if (iteratedTemplate.id == this.currentTemplateId) {
                        return iteratedTemplate;
                    }

                    return foundTemplate;
                }, false);
            },
            isCurrentTemplatePersonal() {
                return this.currentTemplate && this.currentTemplate.type === 'personal';
            },
        }
    }
</script>

<style scoped>

</style>