go.modules.community.pages.PageDialog = Ext.extend(go.form.Dialog, {
	title: t("Page"),
	entityStore: go.Stores.get("Page"),
	width: dp(1200),
	height: dp(600),
	//maximized: true,
	maximizable: true,
	siteId: '',
	sortOrder: '',
	initFormItems: function () {
		var items = [{
				xtype: 'fieldset',
				autoHeight: true,
				items: [
					{
						xtype: 'textfield',
						name: 'pageName',
						fieldLabel: t("Page name"),
						anchor: '100%',
						allowBlank: false
					},
					{
						xtype: 'phtmleditor',
						name: 'content',
						fieldLabel: "",
						hideLabel: true,
						anchor: '100%',
						allowBlank: true,
						enableColors: false,
						enableFont: false,
						enableFontSize: false,
						//enableSourceEdit: false
						
					},
					{
						xtype: 'hidden',
						name: 'siteId',
						value: this.siteId
					},{
						xtype: 'hidden',
						name: 'sortOrder',
						value: this.sortOrder
					}]
			}
		]
		return items;
	},
	
	submit: function(){
	    //todo: check if form has dirty fields.
	    go.modules.community.pages.PageDialog.superclass.submit.call(this);
	}
});