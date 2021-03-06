go.modules.comments.Composer = Ext.extend(go.form.EntityPanel, {
	
	entityStore: "Comment",
	layout: 'border',
	cls:'go-form new-message',
	autoScroll: false,
	minComposerHeight: dp(32),

	initComponent : function() {

		this.store = new go.data.Store({
			fields: ['id', 'name', 'color'],
			entityStore: "CommentLabel"
		});
		
		this.addBtn = new Ext.Button({
			tooltip: t('Add'),
			iconCls: 'ic-add',
			region:"west",
			menu: {
				items:[

//					{
//					iconCls: 'ic-attach-file', 
//					text: t('Select file')
//				},{
//					iconCls: 'ic-file-upload', 
//					text: t('Upload file')
//				},'-'
//				},{
//					iconCls: 'ic-link', 
//					text: t('Add Link')
//				}
			]
			}
		});
		this.store.on('load', function() {
			this.loadLabels();
		},this);

		this.textField = new go.form.HtmlEditor({
			iframePad: 0,
			//enableColors: false,
			enableFont: false,
			enableFontSize: false,
			enableAlignments: false,
			enableSourceEdit: false,
			hideToolbar: true,
			// emptyText: t('Add comment')+'...',
			allowBlank: false,
			plugins: [go.form.HtmlEditor.emojiPlugin],
			height: this.minComposerHeight,
			name: 'text',
			boxMaxHeight: 200,
			boxMinHeight: this.minComposerHeight,
			listeners: {
				ctrlenter: function() {
					this.sendBtn.handler.call(this);
				},
				scope: this
			}
		});
		this.textField.on('sync', this.onSync,this);	
		this.textField.on("initialize", this.onSync, this);
		this.textField.on('afterrender', this.onSync,this);
		
		this.sendBtn = new Ext.Button({
			region:"east",
			tooltip: t('Send'),
			iconCls: 'ic-send',
			handler: function(){ 
				this.submit();
				this.textField.reset();
				this.chips.reset();
				this.textField.setHeight(this.minComposerHeight);
				// this.loadLabels();
				this.textField.syncValue();
				// this.textfield.focus();
			},
			scope: this
		});
		
		this.items = [
			this.addBtn,
			this.middleBox = new Ext.Container({
				region:"center",
				layout:'anchor',
				defaults: {
					anchor: "100%"
				},
				// align: "stretch",
				// flex: 1,

				items: [
					this.commentBox = new Ext.Container({
						boxMinHeight: this.minComposerHeight,
						layout:'fit',
						frame: true,
						items:[this.textField]
					}),
					this.chips = new go.form.Chips({
						name: 'labels',
						entityStore: 'CommentLabel',
						style:'padding-bottom:4px',
						store: this.store
					}),
					this.attachmentBox = new Ext.Container()
				]
			}),
			this.sendBtn
		]
		
		this.textField.on('afterrender', function() {
			this.store.load();
			this.grow();
		}, this);
		
		
		go.modules.comments.Composer.superclass.initComponent.call(this);

	},

	onSync : function(me) {
		//me.onResize();
		var body = me.getEditorBody(),
		composer = this;
		body.style.height = 'auto';
		body.style.display = 'inline-block';

		body.style.minHeight =  dp(32);
		body.style.padding = dp(8);
		body.style.boxSizing = "border-box";
		body.style.width = "100%";
		
		setTimeout(function() {
			var h =  Math.max(me.boxMinHeight,Math.min(body.offsetHeight, me.boxMaxHeight)); // 400  max height
			if(h > 36) {
				me.tb.show();
				me.tb.doLayout();
			} else {
				me.tb.hide();
			}
			me.ownerCt.setHeight(h + me.tb.el.getHeight());
			composer.grow();
		}, 0);
	},
	
	grow: function(){
		
		var totalHeight = this.commentBox.getHeight() + this.chips.getHeight() + this.attachmentBox.getHeight();
		this.setHeight(totalHeight);
		this.middleBox.setHeight(totalHeight-2);
		var headerHeight = (this.header || this.ownerCt.header) ? dp(42) : 0;
		var h = Math.min(this.ownerCt.growMaxHeight,this.ownerCt.commentsContainer.getEl().dom.scrollHeight + totalHeight + headerHeight);
		h += dp(14);
		this.ownerCt.setHeight(h);
		this.ownerCt.doLayout();	
		this.doLayout();

	},


	
	initEntity : function(entityId,entity, section) {
		this.setValues({
			entityId: entityId,
			entity: entity,
			section: section
		});
	},
	
	loadLabels : function() {
		this.addBtn.menu.removeAll();
		this.store.each(function(r) {
			this.addBtn.menu.add({
				text: r.get('name'),
				iconCls: 'ic-label',
				record: r,
				iconStyle: 'color: #'+r.get('color'),
				handler: function(me) {
					this.chips.dataView.store.add([me.record]);
					this.loadLabels(); //redraw
					this.doLayout();
					this.grow();
				},
				scope:this
			});

		}, this);
	}
	
	
});
