import { Application } from '@hotwired/stimulus';
import CsrfProtectionController from './controllers/csrf_protection_controller';
import HelloController from './controllers/hello_controller';
import UtilityController from './controllers/utility_controller';

const app = Application.start();

app.register('csrf-protection', CsrfProtectionController);
app.register('hello', HelloController);
app.register('utility', UtilityController);
