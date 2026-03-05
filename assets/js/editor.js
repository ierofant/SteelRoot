/**
 * editor.js — Lightweight WYSIWYG for admin textarea.shop-editor
 * No dependencies. Uses contenteditable + execCommand.
 */
(function () {
    'use strict';

    var TOOLS = [
        { cmd: 'bold',               label: 'B',       title: 'Bold'          },
        { cmd: 'italic',             label: 'I',       title: 'Italic'        },
        { cmd: 'underline',          label: 'U',       title: 'Underline'     },
        { sep: true },
        { cmd: 'block', val: 'H2',   label: 'H2',      title: 'Heading 2'     },
        { cmd: 'block', val: 'H3',   label: 'H3',      title: 'Heading 3'     },
        { cmd: 'block', val: 'P',    label: '\u00b6',  title: 'Paragraph'     },
        { sep: true },
        { cmd: 'insertUnorderedList', label: '\u2022 List', title: 'Bullet list'  },
        { cmd: 'insertOrderedList',   label: '1. List',     title: 'Ordered list' },
        { sep: true },
        { cmd: 'link',               label: 'Link',    title: 'Insert link'   },
        { cmd: 'unlink',             label: '\u00d7Lnk', title: 'Remove link' },
        { sep: true },
        { cmd: 'html',               label: '</>',     title: 'Toggle HTML source' },
    ];

    function init(ta) {
        var wrap = document.createElement('div');
        wrap.className = 'editor-wrap';

        var bar = document.createElement('div');
        bar.className = 'editor-toolbar';

        var area = document.createElement('div');
        area.className = 'editor-content';
        area.contentEditable = 'true';
        area.innerHTML = ta.value;

        var htmlMode = false;
        var htmlBtn  = null;

        function sync() {
            ta.value = htmlMode ? area.textContent : area.innerHTML;
        }

        area.addEventListener('input', sync);
        area.addEventListener('blur',  sync);

        TOOLS.forEach(function (item) {
            if (item.sep) {
                var s = document.createElement('span');
                s.className = 'editor-sep';
                bar.appendChild(s);
                return;
            }

            var btn = document.createElement('button');
            btn.type      = 'button';
            btn.className = 'editor-btn';
            btn.title     = item.title;
            btn.textContent = item.label;

            switch (item.cmd) {
                case 'html':
                    htmlBtn = btn;
                    btn.addEventListener('click', function () {
                        if (!htmlMode) {
                            var src = area.innerHTML;
                            area.textContent = src;
                            area.classList.add('editor-content--src');
                            btn.classList.add('editor-btn--active');
                            htmlMode = true;
                            ta.value = src;
                        } else {
                            var html = area.textContent;
                            area.innerHTML = html;
                            area.classList.remove('editor-content--src');
                            btn.classList.remove('editor-btn--active');
                            htmlMode = false;
                            ta.value = html;
                        }
                    });
                    break;

                case 'link':
                    btn.addEventListener('click', function () {
                        var url = window.prompt('URL:', 'https://');
                        if (url) {
                            document.execCommand('createLink', false, url);
                            area.focus();
                            sync();
                        }
                    });
                    break;

                case 'block':
                    btn.addEventListener('click', function () {
                        document.execCommand('formatBlock', false, item.val);
                        area.focus();
                        sync();
                    });
                    break;

                default:
                    btn.addEventListener('click', function () {
                        document.execCommand(item.cmd, false, null);
                        area.focus();
                        sync();
                    });
            }

            bar.appendChild(btn);
        });

        ta.parentNode.insertBefore(wrap, ta);
        wrap.appendChild(bar);
        wrap.appendChild(area);
        ta.hidden = true;
        wrap.appendChild(ta);

        if (ta.form) {
            ta.form.addEventListener('submit', sync, { capture: true });
        }
    }

    document.querySelectorAll('textarea.shop-editor').forEach(init);

}());
