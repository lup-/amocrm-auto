<template>
    <b-container fluid class="px-0" id="video">
        <loader v-if="isLoading"></loader>
        <div v-else>
            <b-navbar toggleable type="dark" variant="primary" class="sticky-navbar">
                <b-navbar-brand href="#">Уроки вождения</b-navbar-brand>
                <b-navbar-toggle target="nav-menu"></b-navbar-toggle>

                <b-collapse id="nav-menu" is-nav>
                    <b-navbar-nav>
                        <b-nav-item class="" :href="profileUrl">Мои данные</b-nav-item>
                        <b-nav-item class="active">Уроки</b-nav-item>
                        <b-nav-item class="" :href="ticketsUrl">Билеты</b-nav-item>
                    </b-navbar-nav>
                </b-collapse>
            </b-navbar>

            <b-container class="mt-4 px-2 px-mb-0">
                <b-card no-body class="mb-2" v-for="(item, index) in video" :key="item.id">
                    <b-card-header header-tag="header" class="p-1">
                        <b-button block v-b-toggle="'heading'+index" variant="link" class="text-center" @click="toggleVisible(item)">
                            {{item.title}}
                        </b-button>
                    </b-card-header>
                    <b-collapse :id="'heading'+index" :accordion="'groupAccordion'+item.id" v-model="visibility[item.id]">
                        <b-card-body class="d-flex flex-column justify-content-center">
                            <div class="d-flex justify-content-center">
                                <iframe v-if="isVisible(item)"
                                        :width="videoWidth"
                                        :height="videoHeight"
                                        :src="'https://www.youtube.com/embed/'+getYoutubeId(item.youtubeUrl)"
                                        frameborder="0"
                                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                        allowfullscreen
                                ></iframe>
                            </div>
                            <b-button class="mt-4" variant="primary" :href="ticketsUrl" target="_blank">Билеты и экзамен</b-button>
                        </b-card-body>
                    </b-collapse>
                </b-card>
            </b-container>
        </div>
    </b-container>
</template>

<script>
    import Loader from "./components/Loader";
    import {loadApiData} from "@/modules/api";

    export default {
        name: "Video",
        components: {
            Loader,
        },
        data() {
            return {
                isLoading: true,
                video: false,
                baseVideoWidth: 560,
                baseVideoHeight: 315,
                visibility: {},
            }
        },
        async beforeMount() {
            await this.loadVideo();
            this.isLoading = false;
        },
        methods: {
            async loadVideo() {
                let params = {
                    type: 'getVideo',
                };
                let responseData = await loadApiData(params);
                this.video = responseData.video;
            },
            getYoutubeId(url) {
                return url.replace('https://www.youtube.com/watch?v=', '');
            },
            toggleVisible(item) {
                this.$set(this.visibility, item.id, !this.isVisible(item));
            },
            isVisible(item) {
                return this.visibility[ item.id ] || false;
            }
        },
        computed: {
            ticketsUrl() {
                return location.href.replace('video.html', 'tickets.html');
            },
            profileUrl() {
                return location.href.replace('video.html', 'user.html');
            },
            aspectRatio() {
                return this.baseVideoWidth / this.baseVideoHeight;
            },
            videoWidth() {
                if (window.innerWidth > this.baseVideoWidth) {
                    return this.baseVideoWidth;
                }

                return window.innerWidth;
            },
            videoHeight() {
                return this.videoWidth / this.aspectRatio;
            }
        }
    }
</script>

<style scoped>

</style>