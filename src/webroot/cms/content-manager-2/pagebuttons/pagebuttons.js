//Invoke strict mode
"use strict";

/**
 * Create and show/hide specific buttons when required
 */
SU('anim', function (Y) {

	var BUTTON_DEFAULTS = {
		'cancel':	{'label': 'Cancel'},
		'close':	{'label': 'Close'},
		'save': 	{'label': 'Save'},
		'publish':	{'label': 'Publish', 'style': 'mid-blue'},
		'apply':	{'label': 'Apply', 'style': 'mid-blue'},
		'done':		{'label': 'Done', 'style': 'mid-blue'}
	};
	var BUTTON_DEFAULT_CONF = {
		'type': 'button',
		'style': 'mid'
	};
	
	//Animations
	var ANIMATION = {
		'up_out': {
			'from': {top: 0, opacity: 1},
			'to':   {top: -50, opacity: 0}
		},
		'down_out': {
			'from': {top: 0, opacity: 1},
			'to':   {top: 50, opacity: 0}
		},
		'down_in': {
			'from': {top: -50, opacity: 0},
			'to':   {top: 0, opacity: 1}
		},
		'up_in': {
			'from': {top: 50, opacity: 0},
			'to':   {top: 0, opacity: 1}
		}
	};
	
	//Shortcut
	var Manager = SU.Manager;
	var Action = Manager.Action;
	
	//Create Action class
	new Action({
		
		/**
		 * Unique action name
		 * @type {String}
		 */
		NAME: 'PageButtons',
		
		/**
		 * No need for template
		 * @type {Boolean}
		 */
		HAS_TEMPLATE: false,
		
		/**
		 * Currently visible action
		 * @type {String}
		 */
		active_action: null,
		
		/**
		 * Button list
		 * @type {Object}
		 */
		buttons: {},
		
		/**
		 * Button group list (Nodes)
		 * @type {Object}
		 */
		groups: {},
		
		/**
		 * List of previous actions IDs
		 * Used to show previous action buttons when action is hidden
		 * @type {Array}
		 */
		history: ['Root'],
		
		/**
		 * Animation queue
		 * @type {Array}
		 */
		animationQueue: [],
		
		/**
		 * Animation running
		 * @type {Boolean}
		 */
		animationRunning: false,
		
		/**
		 * Run next animation from queue
		 */
		animate: function () {
			if (this.animationRunning) return;
			this.cleanUpAnimations();
			if (!this.animationQueue.length) return;
			
			this.animationRunning = true;
			
			var show = this.animationQueue.shift(),
				showNode = this.groups[show.action_id],
				showIndex = show.index,
				showAnim = null,
				hide = null,
				hideNode = null,
				hideIndex = -2,
				hideAnim = null,
				animName = 'up';
			
			if (!show.visible) {
				hide = show;
				hideNode = showNode;
				hideIndex = showIndex;
				
				show = this.animationQueue.shift();
				showIndex = hideIndex + 1;
				if (show) {
					showNode = this.groups[show.action_id];
					if (show.index != -1) showIndex = show.index;
				}
				
				//Determine if animation should go up or down
				if (hideIndex == showIndex) hideIndex++;
			}
			
			animName = hideIndex < showIndex ? 'up' : 'down';
			
			if (hide) {
				hideAnim = new Y.Anim(Supra.mix({
					node: hideNode,
					duration: 0.35,
					easing: Y.Easing.easeOut
				}, ANIMATION[animName + '_out']));
			}
			if (show) {
				showAnim = new Y.Anim(Supra.mix({
					node: showNode,
					duration: 0.35,
					easing: Y.Easing.easeOut
				}, ANIMATION[animName + '_in']));
			}
			
			(hideAnim || showAnim).on('end', function () {
				this.animationRunning = false;
				this.animate();
			}, this);
			
			if (hideAnim) hideAnim.run();
			if (showAnim) showAnim.run();
		},
		
		/**
		 * Remove sequential animations which shows and immediately hides toolbar (or vise versa)
		 */
		cleanUpAnimations: function () {
			if (this.animationQueue.length < 2) return;
			var queue = this.animationQueue,
				newQueue = [queue[0]],
				prev_id = queue[0].action_id,
				prev_visible = queue[0].visible,
				changed = false;
			
			for(var i=1,ii=queue.length; i<ii; i++) {
				if (queue[i].action_id == prev_id && queue[i].visible != prev_visible) {
					newQueue.pop();
					prev_id = null;
					prev_visible = null;
					changed = true;
				} else {
					newQueue.push(queue[i]);
					prev_id = queue[i].action_id;
					prev_visible = queue[i].visible;
				}
			}
			
			if (changed) {
				//If animation was removed check again
				this.animationQueue = newQueue;
				this.cleanUpAnimations();
			}
		},
		
		/**
		 * Add buttons, which will be shown for specific action
		 * 
		 * @param {String} actionId
		 * @param {Array} buttons
		 */
		addActionButtons: function (action_id, config) {
			var buttons = [],
				button = null,
				conf = null,
				container = this.get('contentBox'),
				subcontainer = Y.Node.create('<div class="yui3-page-buttons-group"></div>');
			
			if (!container) {
				container = Y.Node.create('<div class="yui3-page-buttons"></div>');
				this.set('contentBox', container);
				Manager.LayoutTopContainer.get('contentBox').append(container);
			}
			
			container.append(subcontainer);
			
			for(var i=0,ii=config.length; i<ii; i++) {
				conf = config[i];
				if (typeof conf == 'string' && conf in BUTTON_DEFAULTS) conf = BUTTON_DEFAULTS[conf];
				else if (conf && conf.id && conf.id in BUTTON_DEFAULTS) conf = Supra.mix({}, BUTTON_DEFAULTS[conf.id], conf);
				
				conf = Supra.mix({}, BUTTON_DEFAULT_CONF, conf);
				
				button = new Supra.Button(conf);
				if (conf.callback && Y.Lang.isFunction(conf.callback)) {
					button.on('click', conf.callback);
				}
				
				buttons.push(button);
				button.render(subcontainer);
			}
			
			this.groups[action_id] = subcontainer;
			this.buttons[action_id] = buttons;
		},
		
		setActiveAction: function (action_id) {
			var old_animation_index = null;
			
			if (!action_id && this.active_action) {
				old_animation_index = Y.Array.indexOf(this.history, this.active_action);
				this.removeHistory(this.active_action);
				action_id = this.history[this.history.length-1];
			}
			
			if (action_id != this.active_action) {
				
				if (this.active_action) {
					if (old_animation_index === null) old_animation_index = Y.Array.indexOf(this.history, this.active_action); 
					this.animationQueue.push({'action_id': this.active_action, 'visible': false, 'index': old_animation_index});
				}
				
				if (action_id && action_id in this.buttons) {
					this.animationQueue.push({'action_id': action_id, 'visible': true, 'index': Y.Array.indexOf(this.history, action_id)});
					this.addHistory(action_id);
				} else {
					action_id = null;
				}
				
				this.active_action = action_id;
				
				/*
				 * If next animation which will be added is the same as this, then
				 * ignore
				 */
				setTimeout(Y.bind(this.animate, this), 10);
			}
		},
		
		unsetActiveAction: function (action_id) {
			if (action_id && action_id == this.active_action) {
				this.setActiveAction(null);
			}
		},
		
		/**
		 * Add action to history to revert to it later if needed
		 * If action is found already in history, then all history about actions
		 * which were opened after it is removed 
		 *  
		 * @param {String} action_id Action ID
		 */
		addHistory: function (action_id) {
			var history = this.history;
			for(var i=0,ii=history.length; i<ii; i++) {
				if (history[i] == action_id) {
					this.history = history.splice(0, i + 1);
					return this;
				}
			}
			this.history.push(action_id);
			return this;
		},
		
		/**
		 * Removes action and all actions which were opened after it
		 * 
		 * @param {String} action_id Action ID
		 */
		removeHistory: function (action_id) {
			var history = this.history;
			for(var i=0,ii=history.length; i<ii; i++) {
				if (history[i] == action_id) {
					this.history = history.splice(0, i);
					return this;
				}
			}
			return this;
		},
		
		/**
		 * Hide
		 */
		hide: function () {
			Action.Base.prototype.hide.apply(this, arguments);
			this.unsetActiveAction(this.active_action);
		}
	});
	
});