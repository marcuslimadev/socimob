
const fs = require('fs');
const path = 'public/portal/index.html';
let text = fs.readFileSync(path, 'utf8');
const start = text.indexOf('        function fixText(text) {');
if (start === -1) throw new Error('start not found');
const endMarker = '            return value;\r\n        }';
let end = text.indexOf(endMarker, start);
if (end === -1) throw new Error('end marker not found');
end += endMarker.length;
const newBlock = 
        function fixText(text) {
            const safeText = text == null ? '' : text;
            let value = String(safeText);
            if (!value) return value;

            const replacements = {
                'ImÃ³veis': 'Imóveis',
                'ImÃ³vel': 'Imóvel',
                'imÃ³veis': 'imóveis',
                'imÃ³vel': 'imóvel',
                'opÃ§Ãµes': 'opções',
                'vocÃª': 'você',
                'DescriÃ§Ã£o': 'Descrição',
                'descriÃ§Ã£o': 'descrição',
                'ObservaÃ§Ãµes': 'Observações',
                'preÃ§o': 'preço',
                'PreÃ§o': 'Preço',
                'EndereÃ§o': 'Endereço',
                'endereÃ§o': 'endereço',
                'Imveis': 'Imóveis',
                'Imvel': 'Imóvel'
            };

            Object.keys(replacements).forEach(key => {
                value = value.split(key).join(replacements[key]);
            });

            if (/[\u00c3\u00c2\uFFFD]/.test(value)) {
                try {
                    value = decodeURIComponent(escape(value));
                } catch (e) {
                    // Keep original value if decode fails.
                }
            }

            return value;
        }
