const fs = require('fs');
const path = 'public/portal/index.html';
let text = fs.readFileSync(path, 'utf8');
const start = text.indexOf('        function fixText(text) {');
if (start === -1) throw new Error('start not found');
const endMarker = '            return value;\r\n        }';
let end = text.indexOf(endMarker, start);
if (end === -1) throw new Error('end marker not found');
end += endMarker.length;
const blockLines = [
const blockLines = [
