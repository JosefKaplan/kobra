// Front bundle: themed Bootstrap CSS, icons, navbar collapse, dropdown menus, dismissible alerts, form validation.
import './scss/front.scss';
import 'bootstrap-icons/font/bootstrap-icons.css';
import 'bootstrap/js/dist/collapse';
import 'bootstrap/js/dist/dropdown';
import 'bootstrap/js/dist/alert';
import netteForms from 'nette-forms';

netteForms.initOnLoad();
