<template>
    <section class="tab-content px-3 pt-4">
        <b-row class="tab-pane fade show active">
            <h2 class="text-center">Документы</h2>

            <b-dropdown :text="currentCity ? currentCity : 'Выбор города'" variant="primary" block class="mb-2">
                <b-dropdown-item v-for="city in cities"
                        @click="updateCurrentCity(city)"
                        :key="city"
                >{{city}}</b-dropdown-item>
            </b-dropdown>

            <b-dropdown v-if="currentCity" :text="currentGroup ? 'Группа ' + currentGroup.name : 'Выбор группы'" variant="primary" block class="mb-2">
                <div class="pl-2 pr-4"><b-form-input v-model="groupSearch" placeholder="Поиск" class="m-2"></b-form-input></div>
                <b-dropdown-divider></b-dropdown-divider>
                <b-dropdown-item v-for="group in filteredGroups"
                    @click="updateCurrentGroup(group)"
                    :key="'grp'+group.name"
                >{{group.name}}</b-dropdown-item>
            </b-dropdown>

            <b-form-row v-if="currentGroup" class="align-items-center mt-4 px-4">
                <b-col>
                    <b-form-checkbox @change="toggleSelectAll">Выделить всех</b-form-checkbox>
                </b-col>
                <b-col class="text-right">
                    <b-overlay :show="multipleProcessing" rounded opacity="0.6" spinner-small spinner-variant="primary" class="d-inline-block">
                        <b-button v-if="currentTemplate && isCurrentTemplatePersonal && selectedRecords.length > 0"
                            variant="primary"
                            class="mr-2 mb-2"
                            :disabled="multipleProcessing"
                            @click="createMultipleDocuments"
                        >Сформировать для выбранных</b-button>
                    </b-overlay>
                    <b-dropdown :text="currentTemplate ? currentTemplate.title : 'Выбор шаблона'" right variant="primary" class="mb-2">
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
                </b-col>
            </b-form-row>

            <b-form-group  v-if="currentGroup">
                <b-form-checkbox-group v-model="selectedRecords">
                    <ul class="list-group">
                        <li class="list-group-item" v-for="student in currentGroup.students" :key="student.id">
                            <div class="d-md-flex flex-row">
                                <b-form-checkbox :value="student.id" class="mr-4 flex-fill align-items-center">
                                    <span>{{student.name}}</span>
                                    <b-button variant="link" :href="getStudentLink(student)" target="_blank"><b-icon-box-arrow-up-right></b-icon-box-arrow-up-right></b-button>
                                </b-form-checkbox>

                                <div class="row-buttons text-right">
                                    <b-button v-if="student.docs.length"
                                            v-b-toggle="'collapse-'+student.id"
                                            variant="primary" class="mr-2"
                                    >Готовые документы</b-button>

                                    <b-overlay :show="isLoading(student.id)" opacity="0.6" spinner-small spinner-variant="primary" class="d-inline-block">
                                        <button class="btn btn-primary"
                                                v-if="currentTemplate && isCurrentTemplatePersonal"
                                                :disabled="isLoading(student.id)"
                                                @click="createSelectedDocument(student.id)"
                                        >
                                            <b-icon-download></b-icon-download>
                                        </button>
                                    </b-overlay>
                                </div>
                            </div>

                            <b-row>
                                <b-col>
                                    <b-collapse :id="'collapse-'+student.id" class="mt-2">
                                        <b-list-group>
                                            <b-list-group-item v-for="doc in student.docs" :key="doc.googleId">
                                                <label>{{doc.filename}}</label>
                                                <b-button variant="link" :href="doc.downloadUrl" class="mr-4" target="_blank"><b-icon-download></b-icon-download></b-button>
                                                <b-button variant="link" :href="doc.editUrl" target="_blank"><b-icon-pencil-square></b-icon-pencil-square></b-button>
                                                <b-button variant="link" @click="deleteDocument(doc)"><b-icon-trash-fill></b-icon-trash-fill></b-button>
                                            </b-list-group-item>
                                        </b-list-group>
                                    </b-collapse>
                                </b-col>
                            </b-row>
                        </li>
                    </ul>
                </b-form-checkbox-group>
            </b-form-group>

            <b-overlay :show="groupProcessing" opacity="0.6" spinner-small spinner-variant="primary" class="d-inline-block w-100">
                <button v-if="currentGroup && currentTemplate && !isCurrentTemplatePersonal"
                        :disabled="groupProcessing"
                        class="btn btn-primary btn-block my-4"
                        @click="createSelectedGroupDocument(currentGroup)"
                >
                    Сделать документ для группы {{selectedRecords.length > 0 ? '(выбранные)' : '(все в списке)'}}
                </button>
            </b-overlay>

            <b-list-group v-if="currentGroup.docs">
                <b-list-group-item v-for="doc in currentGroup.docs" :key="doc.googleId" class="d-flex align-items-center">
                    <label class="flex-fill pb-0 mb-0">{{doc.filename}}</label>
                    <b-button variant="link" :href="doc.downloadUrl" class="mr-4" target="_blank"><b-icon-download></b-icon-download></b-button>
                    <b-button variant="link" :href="doc.editUrl" target="_blank"><b-icon-pencil-square></b-icon-pencil-square></b-button>
                    <b-button variant="link" @click="deleteGroupDocument(doc)"><b-icon-trash-fill></b-icon-trash-fill></b-button>
                </b-list-group-item>
            </b-list-group>

        </b-row>
    </section>
</template>

<script>
    import {clone} from "../modules/objectsArrays";
    import StudentMixin from "../mixins/StudentMixin";
    import axios from "axios";

    export default {
        name: "Docs",
        props: ['groups', 'templates', 'instructors'],
        mixins: [StudentMixin],
        data() {
            return {
                currentCity: 'Железнодорожный',
                currentTemplateId: false,
                currentGroup: false,
                selectedRecords: [],
                processing: [],
                groupProcessing: false,
                multipleProcessing: false,
                groupSearch: '',
            }
        },
        watch: {
            groups() {
                if (this.currentGroup) {
                    let newGroup = this.groups.find(group => group.name === this.currentGroup.name);
                    if (newGroup) {
                        this.currentGroup = newGroup;
                    }
                    else {
                        this.currentGroup = false;
                    }
                }
            }
        },
        methods: {
            toggleSelectAll(selected) {
                if (selected) {
                    this.selectedRecords = this.currentGroup.students.map(student => student.id);
                }
                else {
                    this.selectedRecords = [];
                }
            },
            updateCurrentCity(newCity) {
                this.currentCity = newCity;
            },
            updateCurrentTemplateId(newTemplateId) {
                this.currentTemplateId = newTemplateId;
            },
            updateCurrentGroup(newGroup) {
                this.currentGroup = newGroup;
                this.selectedRecords = [];
            },
            isLoading(studentId) {
                return this.processing.indexOf(studentId) !== -1;
            },
            async createMultipleDocuments() {
                this.multipleProcessing = true;
                this.processing = this.processing.concat(this.selectedRecords);

                for (let selectedId of this.selectedRecords) {
                    await this.createSelectedDocument(selectedId);
                }

                this.multipleProcessing = false;
            },
            async createSelectedDocument(studentId) {
                let processingIndex = this.processing.indexOf(studentId);
                if (processingIndex === -1) {
                    this.processing.push(studentId);
                }

                let docResult = await axios.get('/files.php', {
                    params: {
                        action: 'makedocajax',
                        templateId: this.currentTemplateId,
                        leadId: studentId,
                    }
                });

                let newDoc = docResult.data.doc;
                this.$emit('newDoc', this.currentGroup, studentId, newDoc);

                processingIndex = this.processing.indexOf(studentId);
                this.processing.splice(processingIndex, 1);
            },
            async deleteDocument(studentId, doc) {
                let docResult = await axios.get('/files.php', {
                    params: {
                        action: 'delete',
                        googleId: doc.googleId,
                    }
                });

                let deleted = docResult.data.deleted;
                if (deleted) {
                    this.$emit('deleteDoc', this.currentGroup, studentId, doc);
                }
            },
            async createSelectedGroupDocument(group) {
                this.groupProcessing = true;

                let docResult = await axios.get('/files.php', {
                    params: {
                        action: 'makegroupdocajax',
                        templateId: this.currentTemplateId,
                        group: group.name,
                        selected: this.selectedRecords,
                    }
                });

                let newDoc = docResult.data.doc;
                this.$emit('newGroupDoc', this.currentGroup, newDoc);

                this.groupProcessing = false;
            },
            async deleteGroupDocument(doc) {
                let docResult = await axios.get('/files.php', {
                    params: {
                        action: 'delete',
                        googleId: doc.googleId,
                    }
                });

                let deleted = docResult.data.deleted;
                if (deleted) {
                    this.$emit('deleteGroupDoc', this.currentGroup, doc);
                }
            },
            downloadSelectedDocument(studentId) {
                window.location.href = `/files.php?action=makedoc&templateId=${this.currentTemplateId}&leadId=${studentId}`;
            },
            downloadSelectedGroupDocument(group) {
                let selected = this.selectedRecords.map(studentId => `&selected[]=${studentId}`).join('');
                window.location.href = `/files.php?action=makegroupdoc&templateId=${this.currentTemplateId}&group=${group.name}${selected}`;
            }
        },
        computed: {
            filteredGroups() {
                let maxGroups = 10;
                if (!this.groupSearch) {
                    return this.groups.slice().reverse().slice(0, maxGroups);
                }
                else {
                    return this.groups
                        .filter(group => group.name.toLowerCase().indexOf(this.groupSearch.toLowerCase()) !== -1)
                        .reverse().slice(0, maxGroups);
                }
            },
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