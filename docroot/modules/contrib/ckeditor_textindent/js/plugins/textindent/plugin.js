CKEDITOR.plugins.add( 'textindent', {
	icons: 'textindent',
    availableLangs: {'en':1, 'zh-cn':1, 'pt-br':1},
    lang: 'en,zh-cn,pt-br',
	init: function( editor ) {

        var indentation = editor.config.indentation;

        if(typeof(indentation) == 'undefined')
            indentation = '2em';
        else if(indentation.match(/^\d+$/)) {
            indentation = indentation + 'px';
        }

        if(editor.ui.addButton){

            editor.ui.addButton( 'textindent', {
                label: editor.lang.textindent.labelName,
                command: 'insert',
                toolbar: 'insert'
            });
        }

        // XSS filter support
        editor.on('instanceReady', function() {
            jQuery(editor.document.$).find('p[data-text-indent]').each(function() {
                this.setAttribute('style', 'text-indent:'+jQuery(this).data('text-indent'));
            });
        });

        editor.on( 'selectionChange', function()
            {
                var style_textindente = new CKEDITOR.style({
                        element: 'p',
                        styles: { 'text-indent': indentation },
                        overrides: [{
                            element: 'text-indent', attributes: { 'size': '0'}
                        }]
                    });

                if( style_textindente.checkActive(editor.elementPath(), editor) )
                   editor.getCommand('insert').setState(CKEDITOR.TRISTATE_ON);
                else
                   editor.getCommand('insert').setState(CKEDITOR.TRISTATE_OFF);

        })

        editor.addCommand("insert", {
            allowedContent: 'p{text-indent}',
            requiredContent: 'p',
            exec: function() {

                var style_textindente = new CKEDITOR.style({
                        element: 'p',
                        styles: { 'text-indent': indentation },
                        attributes:{ 'data-text-indent': indentation },
                        overrides: [{
                            element: 'text-indent', attributes: { 'size': '0'}
                        }]
                    });

                var style_no_textindente = new CKEDITOR.style({
                        element: 'p',
                        styles: { 'text-indent': '0' },
                        attributes:{ 'data-text-indent': 0 },
                        overrides: [{
                            element: 'text-indent', attributes: { 'size': indentation }
                        }]
                    });

                if( style_textindente.checkActive(editor.elementPath(), editor) ){
                    editor.fire('saveSnapshot');
                    editor.applyStyle(style_no_textindente);
                }
                else{
                    editor.fire('saveSnapshot');
                    editor.applyStyle(style_textindente);
                }

            }
        });
	}

});
