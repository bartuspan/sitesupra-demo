//Invoke strict mode
"use strict";

//Add module definitions
SU.addModule('website.template-list', {
	path: 'pagesettings/modules/template-list.js',
	requires: ['widget', 'website.template-list-css']
});
SU.addModule('website.template-list-css', {
	path: 'pagesettings/modules/template-list.css',
	type: 'css'
});

SU.addModule('website.version-list', {
	path: 'pagesettings/modules/version-list.js',
	requires: ['widget', 'website.version-list-css']
});
SU.addModule('website.version-list-css', {
	path: 'pagesettings/modules/version-list.css',
	type: 'css'
});


SU('website.template-list', 'website.version-list', 'supra.form', 'supra.calendar', 'supra.slideshow', function (Y) {
	
	//Shortcut
	var Manager = SU.Manager;
	var Action = Manager.Action;
	var Loader = Manager.Loader;
	
	//Calendar dates
	var DEFAULT_DATES = [
		{'date': '2011-06-16', 'title': 'Select today'},
		{'date': '2011-06-17', 'title': 'Select tomorrow'}
	];
	
	var SLIDE_ROOT = 'slideMain';
	
	//Add as right bar child
	Manager.getAction('LayoutRightContainer').addChildAction('PageSettings');
	
	//Create Action class
	new Action({
		
		/**
		 * Unique action name
		 * @type {String}
		 */
		NAME: 'PageSettings',
		
		/**
		 * Load stylesheet
		 * @type {Boolean}
		 */
		HAS_STYLESHEET: true,
		
		/**
		 * Form instance
		 * @type {Object}
		 */
		form: null,
		
		/**
		 * Slideshow instance
		 * @type {Object}
		 */
		slideshow: null,
		
		/**
		 * Buttons
		 * @type {Object}
		 */
		button_cancel: null,
		button_back: null,
		
		/**
		 * Template list object
		 * @type {Object}
		 */
		template_list: null,
		
		/**
		 * Version list object
		 * @type {Object}
		 */
		version_list: null,
		
		/**
		 * Page data
		 * @type {Object}
		 */
		page_data: {},
		
		/**
		 * On slide change show/hide buttons and call callback function
		 * 
		 * @param {Object} evt
		 */
		onSlideChange: function (evt) {
			var slide_id = evt.newVal;
			
			if (evt.newVal == 'slideMain') {
				//this.button_cancel.show();
				this.button_back.hide();	
			} else {
				//this.button_cancel.hide();
				this.button_back.show();	
			}
			
			//Call "onSlide..." callback function
			var new_item = (slide_id ? Y.one('#' + slide_id) : null),
				fn = slide_id ? 'on' + (slide_id.substr(0,1).toUpperCase() + slide_id.substr(1)) : null;
			
			if (fn && fn in this) {
				this[fn](new_item);
			}
		},
		
		/**
		 * When schedule slide is shown create widget, bind listeners
		 */
		onSlideSchedule: function (node) {
			var date = this.page_data.scheduled_date;
			
			//Create calendar if it doesn't exist
			if (!this.calendar) {
				//Create calendar
				var calendar = this.calendar = new Supra.Calendar({
					'srcNode': node.one('.calendar'),
					'date': date,
					'minDate': new Date(),
					'dates': DEFAULT_DATES
				});
				calendar.render();
				
				//Create apply button
				var btn = new Supra.Button({srcNode: node.one('button')});
				btn.render();
				btn.on('click', this.onSlideScheduleApply, this);
			} else {
				//Set date
				this.calendar.set('date', date);
				this.calendar.set('displayDate', date);
			}
			
			//Set time
			var time = Y.DataType.Date.parse(this.page_data.scheduled_time, {format: '%H:%M'}),
				hours = (time ? time.getHours() : 0),
				minutes = (time ? time.getMinutes() : 0);
			
			this.form.getInput('schedule_hours').set('value', hours < 10 ? '0' + hours : hours);
			this.form.getInput('schedule_minutes').set('value', minutes < 10 ? '0' + minutes : minutes);
		},
		
		/**
		 * On "slideSchedule" slide Apply button click save calendar values
		 */
		onSlideScheduleApply: function () {
			//Save date
			this.page_data.scheduled_date = Y.DataType.Date.format(this.calendar.get('date'));
			
			//Save time
			var inp_h = this.form.getInput('schedule_hours'),
				inp_m = this.form.getInput('schedule_minutes'),
				date = new Date();
			
			date.setHours(parseInt(inp_h.getValue(), 10) || 0);
			date.setMinutes(parseInt(inp_m.getValue(), 10) || 0);
			
			this.page_data.scheduled_time = Y.DataType.Date.format(date, {format: '%H:%M'});
			this.slideshow.scrollBack();
		},
		
		
		/**
		 * When "slideTemplate" slide is shown create widget, bind listeners
		 */
		onSlideTemplate: function (node) {
			if (!this.template_list) {
				this.template_list = new Supra.TemplateList({
					'srcNode': node.one('ul.template-list'),
					'requestUri': this.getPath() + 'templates' + Loader.EXTENSION_DATA,
					'template': this.page_data.template.id
				});
				
				this.template_list.render();
				
				this.template_list.on('change', function (e) {
					this.page_data.template = e.template;
					
					this.setFormValue('template', this.page_data);
					this.slideshow.scrollBack();
				}, this);
			} else {
				this.template_list.set('template', this.page_data.template.id);
			}
		},
		
		/**
		 * When version slide is shown create widget, bind listeners
		 */
		onSlideVersion: function (node) {
			if (!this.version_list) {
				this.version_list = new Supra.VersionList({
					'srcNode': node.one('div.version-list'),
					'requestUri': this.getPath() + 'versions' + Loader.EXTENSION_DATA
				});
				
				this.version_list.render();
				
				this.version_list.on('change', function (e) {
					this.page_data.version = e.version;
					
					this.setFormValue('version', this.page_data);
					this.slideshow.scrollBack();
				}, this);
			}
		},
		
		/**
		 * Render all block list
		 */
		onSlideBlocks: function () {
			var blocks = Manager.PageContent.getContentBlocks(),
				block = null,
				block_type = null,
				block_definition = null,
				container = this.getContainer('ul.block-list'),
				item = null;
			
			container.all('li').remove();
			
			for(var id in blocks) {
				if (!blocks[id].isLocked()) {
					block = blocks[id];
					block_type = block.getType();
					block_definition = Manager.Blocks.getBlock(block_type);
					
					item = Y.Node.create('<li class="clearfix"><div><img src="' + block_definition.icon + '" alt="" /></div><p>' + Y.Lang.escapeHTML(block_definition.title) + '</p></li>');
					item.setData('content_id', id);
					
					container.append(item);
				}
			}
			
			var li = container.all('li');
			li.on('mouseenter', function (evt) {
				var target = evt.target.closest('LI'),
					content_id = target.getData('content_id');
					
				blocks[content_id].set('highlightOverlay', true);
			});
			li.on('mouseleave', function (evt) {
				var target = evt.target.closest('LI'),
					content_id = target.getData('content_id');
				
				blocks[content_id].set('highlightOverlay', false);
			});
			li.on('click', function (evt) {
				this.hide();
				
				var target = evt.target.closest('LI'),
					content_id = target.getData('content_id'),
					contents = null;
				
				//Start editing content
				contents = Manager.PageContent.getContentContainer();
				contents.set('activeContent', blocks[content_id]);
				
				//Show properties form
				if (blocks[content_id].properties) {
					blocks[content_id].properties.showPropertiesForm();
				}
			}, this);
		},
		
		/**
		 * Delete page
		 */
		deletePage: function () {
			Manager.Page.deletePage();
			this.hide();
		},
		
		/**
		 * Create form
		 */
		createForm: function () {
			
			var buttons = this.getContainer().all('button');
			
			//Apply button
			(new Supra.Button({'srcNode': buttons.filter('.button-save').item(0), 'style': 'mid-blue'}))
				.render().on('click', this.saveSettingsChanges, this);
				
			//Close button
			/*
			this.button_cancel = new Supra.Button({'srcNode': buttons.filter('.button-cancel').item(0)});
			this.button_cancel.render().on('click', this.cancelSettingsChanges, this);
			*/
			
			//Back button
			this.button_back = new Supra.Button({'srcNode': buttons.filter('.button-back').item(0)});
			this.button_back.render().hide().on('click', function () { this.slideshow.scrollBack(); }, this);
			
			//Delete button
			(new Supra.Button({'srcNode': buttons.filter('.button-delete').item(0), 'style': 'mid-red'}))
				.render().on('click', this.deletePage, this);
			
			//Meta button
			(new Supra.Button({'srcNode': buttons.filter('.button-meta').item(0)}))
				.render().on('click', function () { this.slideshow.set('slide', 'slideMeta'); }, this);
			
			//Version button
			(new Supra.Button({'srcNode': buttons.filter('.button-version').item(0), 'style': 'large'}))
				.render().on('click', function () { this.slideshow.set('slide', 'slideVersion'); }, this);
			
			//Template button
			(new Supra.Button({'srcNode': buttons.filter('.button-template').item(0), 'style': 'template'}))
				.render().on('click', function () { this.slideshow.set('slide', 'slideTemplate'); }, this);
			
			//Schedule button
			(new Supra.Button({'srcNode': buttons.filter('.button-schedule').item(0)}))
				.render().on('click', function () { this.slideshow.set('slide', 'slideSchedule'); }, this);
			
			//Blocks button
			(new Supra.Button({'srcNode': buttons.filter('.button-blocks').item(0)}))
				.render().on('click', function () { this.slideshow.set('slide', 'slideBlocks'); }, this);
			
			//Slideshow
			var slideshow = this.slideshow = new Supra.Slideshow({
				'srcNode': this.getContainer('div.slideshow')
			});
			slideshow.render();
			slideshow.on('slideChange', this.onSlideChange, this);
			
			//Form
			var form = this.form = new Supra.Form({
				'srcNode': this.getContainer('form')
			});
			form.render();
			
			//When layout position/size changes update slide
			Manager.LayoutRightContainer.layout.on('sync', this.slideshow.syncUI, this.slideshow);
		},
		
		/**
		 * Set form values
		 */
		setFormValues: function () {
			var page_data = this.page_data;
			this.form.setValues(page_data, 'id');
			
			//Set version info
			this.setFormValue('version', page_data);
			
			//Set template info
			this.setFormValue('template', page_data);
		},
		
		/**
		 * Set form value
		 * 
		 * @param {Object} key
		 * @param {Object} value
		 */
		setFormValue: function (key, page_data) {
			switch(key) {
				case 'template':
					//Set version info
					var node = this.getContainer('.button-template small');
					node.one('span').set('text', page_data.template.title);
					node.one('img').set('src', page_data.template.img);
					break;
				case 'version':
					var node = this.getContainer('.button-version small');
					node.one('b').set('text', page_data.version.title);
					node.one('span').set('text', page_data.version.author + ', ' + page_data.version.date);
					break;
				default:
					var obj = {};
					obj[key] = page_data[key];
					this.form.setValues(obj, 'id');
					break;
			}
		},
		
		/**
		 * Save changes
		 */
		saveSettingsChanges: function () {
			var page_data = this.page_data,
				form_data = this.form.getValuesObject();
			
			//Remove unneeded form data for save request
			delete(form_data.template);
			delete(form_data.schedule_hours);
			delete(form_data.schedule_minutes);
			delete(form_data.version_id);
			
			Supra.mix(page_data, form_data);
			
			//Remove unneeded data for save request
			var post_data = Supra.mix({}, page_data);
			post_data.version = post_data.version.id;
			post_data.template = post_data.template.id;
			
			delete(post_data.path_prefix);
			delete(post_data.internal_html);
			delete(post_data.contents);
			
			post_data.language = Supra.data.get('language');
			
			//Save data
			var url = this.getDataPath('save');
			Supra.io(url, {
				'data': post_data,
				'method': 'POST',
				'on': {
					'success': function (transaction, version) {
						if (version) {
							page_data.version = version;
							Manager.Page.setPageData(page_data);
						}
					}
				}
			}, this);
			
			this.hide();
		},
		
		/**
		 * CancelSave changes
		 */
		cancelSettingsChanges: function () {
			this.hide();
		},
		
		/**
		 * Render widgets and add event listeners
		 */
		render: function () {
			this.on('visibleChange', function (evt) {
				if (evt.newVal != evt.prevVal) {
					if (evt.newVal) {
						this.getContainer().removeClass('hidden');
					} else {
						this.slideshow.set('noAnimation', true);
						this.slideshow.scrollBack();
						this.slideshow.set('noAnimation', false);
						
						this.getContainer().addClass('hidden');
					}
				}
			}, this);
		},
		
		/**
		 * Hide action
		 */
		hide: function () {
			Action.Base.prototype.hide.apply(this, arguments);
			//Hide action
			Manager.getAction('LayoutRightContainer').unsetActiveAction(this.NAME);
		},
		
		/**
		 * Execute action
		 */
		execute: function () {
			Manager.getAction('LayoutRightContainer').setActiveAction(this.NAME);
			
			if (!this.form) this.createForm();
			this.page_data = Supra.mix({}, Manager.Page.getPageData());
			this.setFormValues();
			
			this.slideshow.set('noAnimation', true);
			this.slideshow.scrollBack();
			this.slideshow.set('noAnimation', false);
		}
	});
	
});