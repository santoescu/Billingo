import ClipboardJS from 'clipboard';
window.ClipboardJS = ClipboardJS;
import clipboardHelper from 'preline/helpers/clipboard';
window.hsClipboardHelper = clipboardHelper;

import "preline";
import '@preline/select'
import '@preline/overlay';
import '@preline/dropdown';
import '@preline/accordion';
import '@preline/tabs';
import '@preline/input-number';

import _ from 'lodash';
window._ = _;
import Dropzone from 'dropzone';
window.Dropzone = Dropzone;
import '@preline/file-upload';

import $ from 'jquery';

window.$ = window.jQuery = $;

import '@preline/copy-markup';
import 'datatables.net-dt';

import ApexCharts from 'apexcharts';
window.ApexCharts = ApexCharts;
