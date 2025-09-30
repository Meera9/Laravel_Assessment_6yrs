<template>
    <div class="p-4">
        <label>User:</label>
        <select v-model="selectedUser" class="border p-2">
            <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
        </select>

        <div class="mt-4">
            <input type="file" multiple @change="onFiles" />
        </div>

        <ul class="mt-4">
            <li v-for="file in uploads" :key="file.id">
                <strong>{{ file.name }}</strong> — {{ file.progress }}% — <span
                v-if="file.status">{{ file.status }}</span>
            </li>
        </ul>

        <div class="container-fluid">
            <div class="row m-auto">
                <div class="col-12">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Name</th>
                                    <th>Primary Image</th>
                                    <th>Images</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(user,index) in users">
                                    <td>{{ index + 1 }}</td>
                                    <td>{{ user.name }}</td>
                                    <td>
                                        <div v-if="user.primary_image">
                                            <img :src="user.primary_image.path" />
                                        </div>
                                    </td>
                                    <td>
                                        <div class="table-responsive">
                                            <table class="table table-bordered" v-if="user.images">
                                                <thead>
                                                    <tr>
                                                        <td> variant_256</td>
                                                        <td> variant_512</td>
                                                        <td> variant_1024</td>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr v-for="(img,index) in user.images">
                                                        <td><img :src="img.variant_256" /></td>
                                                        <td><img :src="img.variant_512" /></td>
                                                        <td><img :src="img.variant_1024" /></td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot v-if="!users.length">
                                <tr>
                                    <td colspan="3">No record found.</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import axios from 'axios';
import SparkMD5 from 'spark-md5';

export default {
    data() {
        return {
            users: [],
            selectedUser: null,
            uploads: []
        };
    },
    async mounted() {
        const {data} = await axios.get('/api/users');
        this.users = data;
        this.selectedUser = this.users[0]?.id || null;
    },
    methods: {
        onFiles(e) {
            const files = Array.from(e.target.files);
            files.forEach(f => this.processFile(f));
        },

        async processFile(file) {
            if ( !this.selectedUser ) {
                return alert('Select a user first');
            }

            const chunkSize = 1024 * 1024; // 1MB
            const totalChunks = Math.ceil(file.size / chunkSize);
            const checksum = await this.calcMD5(file);

            // init on server
            const initRes = await axios.post('/api/upload/init', {
                user_id: this.selectedUser,
                filename: file.name,
                total_chunks: totalChunks,
                checksum
            });
            const uploadId = initRes.data.upload_id;

            const ui = {id: uploadId, name: file.name, progress: 0, status: 'uploading'};
            this.uploads.push(ui);

            for (let i = 0; i < totalChunks; i++) {
                const start = i * chunkSize;
                const end = Math.min(file.size, start + chunkSize);
                const blob = file.slice(start, end);

                const fd = new FormData();
                fd.append('chunk_index', i);
                fd.append('file', blob);

                await axios.post(`/api/upload/${uploadId}/chunk`, fd, {
                    onUploadProgress: (ev) => {
                        const percent = Math.round(( ( i + ev.loaded / ev.total ) / totalChunks ) * 100);
                        ui.progress = percent;
                    }
                });
            }

            // complete
            const complete = await axios.post(`/api/upload/${uploadId}/complete`);
            this.updateUpload(ui.id, {
                progress: 100,
                status: 'done'
            });
            console.log('complete response', complete.data);
        },

        calcMD5(file) {
            return new Promise((resolve, reject) => {
                const chunkSize = 2 * 1024 * 1024; // 2MB
                const chunks = Math.ceil(file.size / chunkSize);
                let current = 0;
                const spark = new SparkMD5.ArrayBuffer();
                const reader = new FileReader();

                reader.onload = (e) => {
                    spark.append(e.target.result);
                    current++;
                    if ( current < chunks ) {
                        loadNext();
                    } else {
                        const hash = spark.end();
                        resolve(hash);
                    }
                };

                reader.onerror = () => reject('File read error');

                function loadNext() {
                    const start = current * chunkSize;
                    const end = Math.min(start + chunkSize, file.size);
                    reader.readAsArrayBuffer(file.slice(start, end));
                }

                loadNext();
            });
        },

        updateUpload(uploadId, data) {
            const index = this.uploads.findIndex(u => u.id === uploadId);
            if ( index !== -1 ) {
                this.uploads[index] = {...this.uploads[index], ...data};
            }
        }
    }
};
</script>

<style scoped>
</style>
