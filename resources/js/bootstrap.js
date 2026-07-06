import { createApp } from 'vue';
import PrimeVue from 'primevue/config';
import ToastService from 'primevue/toastservice';
import Password from 'primevue/password';
import Checkbox from 'primevue/checkbox';
import Tooltip from 'primevue/tooltip';
import Aura from '@primeuix/themes/aura';
import router from './router';
import App from './App.vue';

import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Tag from 'primevue/tag';
import Card from 'primevue/card';
import Message from 'primevue/message';
import Toast from 'primevue/toast';
import Panel from 'primevue/panel';
import Dialog from 'primevue/dialog';
import Drawer from 'primevue/drawer';
import InputNumber from 'primevue/inputnumber';
import Textarea from 'primevue/textarea';
import ProgressSpinner from 'primevue/progressspinner';
import Avatar from 'primevue/avatar';
import Badge from 'primevue/badge';

const app = createApp(App);

app.use(PrimeVue, {
    theme: {
        preset: Aura,
        options: { prefix: 'p', darkModeSelector: false }
    }
});
app.use(ToastService);
app.use(router);
app.directive('tooltip', Tooltip);

app.component('Button', Button);
app.component('InputText', InputText);
app.component('Select', Select);
app.component('DataTable', DataTable);
app.component('Column', Column);
app.component('Tag', Tag);
app.component('Card', Card);
app.component('Message', Message);
app.component('Toast', Toast);
app.component('Panel', Panel);
app.component('Dialog', Dialog);
app.component('Drawer', Drawer);
app.component('InputNumber', InputNumber);
app.component('Textarea', Textarea);
app.component('ProgressSpinner', ProgressSpinner);
app.component('Avatar', Avatar);
app.component('Badge', Badge);
app.component('Password', Password);
app.component('Checkbox', Checkbox);

app.mount('#app');
