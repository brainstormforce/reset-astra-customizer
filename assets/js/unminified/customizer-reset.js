/**
 * Astra Theme Customizer Reset
 *
 * Adds a "Reset All" menu to the Customizer header with two options:
 * - Reset in Preview: shows the defaults in the preview. Saved on Publish.
 * - Reset and Publish Now: removes the settings in the database right away.
 *
 * @package Astra Customizer Reset
 * @since  1.0.0
 */

( ( api ) => {
	const l10n       = window.astraThemeCustomizerReset.customizer.reset;
	const PREFIX     = 'astra-settings[';
	const NOTICE     = 'astra_customizer_reset_pending';
	const PENDING_ID = 'astra-customizer-reset-pending';

	// True while a confirm dialog is open. The dialog blurs the window, which
	// would otherwise close the menu.
	let confirming = false;

	// Settings without a default changed before the reset, which it removes
	// unless they are changed again. Sent with the "reset pending" flag.
	const STALE_KEY  = /^[A-Za-z0-9_-]+$/;
	const staleKeys  = new Set();
	const staleWatch = new Map();
	let pendingStamp = '';

	/**
	 * Set the "reset pending" flag: "{timestamp}" or "{timestamp}|{key},{key}".
	 */
	const setPending = () => {
		if ( api.has( PENDING_ID ) ) {
			api( PENDING_ID ).set( staleKeys.size ? `${ pendingStamp }|${ [ ...staleKeys ].join( ',' ) }` : pendingStamp );
		}
	};

	/**
	 * Track the stale settings: one changed after the reset is kept.
	 *
	 * @param {Set} keys Setting keys.
	 */
	const watchStale = ( keys ) => {
		staleWatch.forEach( ( handler, id ) => {
			if ( api.has( id ) ) {
				api( id ).unbind( handler );
			}
		} );
		staleWatch.clear();
		staleKeys.clear();

		keys.forEach( ( key ) => {
			const id = `${ PREFIX }${ key }]`;

			if ( ! STALE_KEY.test( key ) || ! api.has( id ) ) {
				return;
			}

			const handler = () => {
				api( id ).unbind( handler );
				staleWatch.delete( id );
				staleKeys.delete( key );
				setPending();
			};

			staleKeys.add( key );
			staleWatch.set( id, handler );
			api( id ).bind( handler );
		} );
	};

	/**
	 * Ask the user to confirm, without the dialog closing the menu.
	 *
	 * @param {string} message Question.
	 * @return {boolean} Whether the user confirmed.
	 */
	const confirmKeepMenu = ( message ) => {
		confirming = true;
		const confirmed = window.confirm( message );
		// The blur event can arrive after the dialog closes.
		setTimeout( () => {
			confirming = false;
		} );
		return confirmed;
	};

	/**
	 * Create an element with attributes and children.
	 *
	 * @param {string} tag      Tag name.
	 * @param {Object} attrs    Attributes. `text` sets textContent.
	 * @param {Array}  children Child nodes.
	 * @return {HTMLElement} The element.
	 */
	const el = ( tag, attrs = {}, children = [] ) => {
		const node = document.createElement( tag );

		Object.entries( attrs ).forEach( ( [ name, value ] ) => {
			if ( 'text' === name ) {
				node.textContent = value;
			} else {
				node.setAttribute( name, value );
			}
		} );

		node.append( ...children );
		return node;
	};

	/**
	 * Deep copy, so the localized defaults are never changed by reference.
	 *
	 * @param {*} value Value to copy.
	 * @return {*} The copy.
	 */
	const clone = ( value ) => JSON.parse( JSON.stringify( value ) );

	/**
	 * Show that a reset is in the preview only until Publish is clicked.
	 */
	const showPendingNotice = () => {
		api.notifications.add(
			new api.Notification( NOTICE, {
				type: 'info',
				message: l10n.stringNotice,
				dismissible: false,
			} )
		);
		// Core announces new notifications to screen readers itself.
	};

	/**
	 * Show the reset as busy (header spinner, disabled button) or not.
	 *
	 * @param {HTMLButtonElement} button The Reset All button.
	 * @param {boolean}           busy   Whether a reset is running.
	 */
	const setResetBusy = ( button, busy ) => {
		const spinner = document.querySelector( '#customize-header-actions .spinner' );

		if ( spinner ) {
			spinner.classList.toggle( 'is-active', busy );
		}
		button.disabled = busy;
	};

	/**
	 * Show an error notification for a failed reset.
	 */
	const showResetError = () => {
		api.notifications.add(
			new api.Notification( 'astra_customizer_reset_error', {
				type: 'error',
				message: l10n.stringError,
				dismissible: true,
			} )
		);
	};

	/**
	 * Save a setting in the changeset even when its value did not change, as
	 * core does on a change.
	 *
	 * @param {Object} setting Customizer setting.
	 */
	const markChanged = ( setting ) => {
		setting._dirty = true;

		if ( api._latestSettingRevisions ) {
			api._latestRevision += 1;
			api._latestSettingRevisions[ setting.id ] = api._latestRevision;
		}
	};

	/**
	 * Reset in Preview: nothing is saved until the user clicks Publish.
	 *
	 * @param {HTMLButtonElement} button The Reset All button.
	 */
	const resetInPreview = async ( button ) => {
		const processing = api.state( 'processing' );

		setResetBusy( button, true );
		// Publish waits until the reset is applied.
		processing.set( processing.get() + 1 );

		try {
			const result = await ajax( {
				action: l10n.defaultsAction,
				nonce: l10n.nonce,
				changeset_uuid: api.settings.changeset.uuid,
			} );

			if ( ! result || ! result.success || ! result.data ) {
				throw new Error( 'No defaults' );
			}

			applyReset( result.data );
			showPendingNotice();
		} catch ( error ) {
			showResetError();
		} finally {
			processing.set( processing.get() - 1 );
			setResetBusy( button, false );
		}
	};

	/**
	 * Set the reset values in the Customizer.
	 *
	 * @param {Object} data Defaults from the server.
	 */
	const applyReset = ( data ) => {
		const defaults = data.defaults || {};
		const stale    = new Set( data.stale || [] );

		api.each( ( setting ) => {
			// Notice boxes only show a message and hold no value.
			if ( ! setting.id.startsWith( PREFIX ) || setting.id.includes( 'ast-callback-notice' ) ) {
				return;
			}

			const key = setting.id.slice( PREFIX.length, -1 );

			if ( Object.prototype.hasOwnProperty.call( defaults, key ) ) {
				setting.set( clone( defaults[ key ] ) );
				markChanged( setting );
			} else if ( setting._dirty ) {
				// No default: the server removes it, also when changed before.
				stale.add( key );
			}
		} );

		// Global color palette. Replace the whole value, like the full reset that
		// deletes the option, so custom colors and palette names are removed too.
		if ( api.has( 'astra-color-palettes' ) && data.paletteDefaults && data.paletteDefaults.palettes ) {
			api( 'astra-color-palettes' ).set( clone( data.paletteDefaults ) );
		}

		// Typography presets are removed by the full reset, so empty them here too.
		if ( api.has( 'astra-typography-presets' ) ) {
			api( 'astra-typography-presets' ).set( '' );
		}

		// The site logo is a theme mod and has no registered default.
		if ( api.has( 'custom_logo' ) ) {
			api( 'custom_logo' ).set( '' );
		}

		// New value each time, so it is sent again after an earlier reset.
		pendingStamp = String( Date.now() );
		watchStale( stale );
		setPending();
	};


	/**
	 * Discard this session's autosave, so it is not offered after the reload.
	 */
	const dismissAutosave = async () => {
		const nonce = api.settings.nonce && api.settings.nonce.dismiss_autosave_or_lock;

		// Stop core from saving the changes again while the page unloads, as its
		// own close button does.
		api.each( ( setting ) => {
			setting._dirty = false;
		} );
		if ( window.jQuery ) {
			window.jQuery( document ).off( 'visibilitychange.wp-customize-changeset-update' );
			window.jQuery( window ).off( 'beforeunload.wp-customize-changeset-update' );
		}

		if ( ! nonce ) {
			return;
		}

		// Wait for a changeset update that is already running.
		await new Promise( ( resolve ) => {
			const processing = api.state( 'processing' );
			const check = () => {
				if ( 0 === processing.get() ) {
					processing.unbind( check );
					resolve();
				}
			};
			processing.bind( check );
			check();
		} );

		// Core skips the auto-draft named in the request, so name a new changeset
		// to dismiss this session's auto-draft too. A draft keeps its own UUID,
		// so only its autosave revision is deleted.
		const status = api.state( 'changesetStatus' ).get();
		const uuid   = '' === status || 'auto-draft' === status ? newUuid() : api.settings.changeset.uuid;

		try {
			await ajax( {
				action: 'customize_dismiss_autosave_or_lock',
				wp_customize: 'on',
				customize_theme: api.settings.theme.stylesheet,
				customize_changeset_uuid: uuid,
				nonce,
				dismiss_autosave: 'true',
			} );
		} catch ( error ) {
			// No autosave to dismiss.
		}
	};

	/**
	 * A random UUID v4. `crypto.randomUUID()` needs HTTPS.
	 *
	 * @return {string} UUID.
	 */
	const newUuid = () =>
		'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace( /[xy]/g, ( char ) => {
			const random = Math.floor( Math.random() * 16 );
			return ( 'x' === char ? random : ( random % 4 ) + 8 ).toString( 16 );
		} );

	/**
	 * Reload the Customizer without the changeset in the URL.
	 */
	const reloadCustomizer = () => {
		api.state( 'saved' ).set( true );
		window.location.href = window.location.href.split( '?' )[ 0 ];
	};

	/**
	 * Reset and Publish Now: the original behaviour. Removes the settings in
	 * the database right away and reloads the Customizer.
	 *
	 * @param {HTMLButtonElement} button The Reset All button.
	 */
	const resetAndPublish = async ( button ) => {
		setResetBusy( button, true );

		try {
			const result = await ajax( {
				wp_customize: 'on',
				action: 'astra_theme_customizer_reset',
				nonce: l10n.nonce,
			} );

			if ( ! result || ! result.success ) {
				throw new Error( 'Reset failed' );
			}
		} catch ( error ) {
			setResetBusy( button, false );
			showResetError();
			return;
		}

		await dismissAutosave();
		reloadCustomizer();
	};

	/**
	 * POST to admin-ajax.php.
	 *
	 * @param {Object} data Fields to send.
	 * @return {Promise<Object>} The JSON response.
	 */
	const ajax = async ( data ) => {
		const response = await fetch( window.ajaxurl, {
			method: 'POST',
			credentials: 'same-origin',
			body: new URLSearchParams( data ),
		} );

		return response.json();
	};

	/**
	 * Show an error notification. The message is HTML built on the server.
	 *
	 * @param {string} message Message.
	 */
	const showBackupError = ( message ) => {
		api.notifications.add(
			new api.Notification( 'astra_customizer_reset_backup_error', {
				type: 'error',
				message,
				dismissible: true,
			} )
		);
	};

	/**
	 * Create the "back up first" menu row: a button, or a link to the plugin
	 * install screen when WordPress needs FTP details to install plugins.
	 *
	 * @param {Object} data Row data from the server.
	 * @return {HTMLElement} The row.
	 */
	const backupItem = ( data ) => {
		const isLink = 'link' === data.action;
		const attrs  = {
			role: 'menuitem',
			class: 'astra-reset-menu-item is-backup',
			tabindex: '-1',
			'data-action': 'backup',
			'aria-labelledby': 'astra-reset-label-backup',
			'aria-describedby': 'astra-reset-help-backup',
		};

		if ( isLink ) {
			// Opens in a new tab so the Customizer and its changes stay open.
			Object.assign( attrs, { href: data.url, target: '_blank', rel: 'noopener noreferrer' } );
		} else {
			attrs.type = 'button';
		}

		const item = el( isLink ? 'a' : 'button', attrs, [
			el( 'span', { class: 'astra-reset-menu-item-label', id: 'astra-reset-label-backup' }, [
				el( 'span', { class: 'dashicons dashicons-download', 'aria-hidden': 'true' } ),
				el( 'span', { class: 'astra-reset-backup-text', text: data.label } ),
				...( isLink
					? [
						el( 'span', { class: 'dashicons dashicons-external', 'aria-hidden': 'true' } ),
						el( 'span', { class: 'screen-reader-text', text: ` ${ data.newTab }` } ),
					]
					: [] ),
			] ),
			el( 'span', { class: 'astra-reset-menu-item-help', id: 'astra-reset-help-backup', text: data.help } ),
		] );

		item.backupData = data;
		return item;
	};

	/**
	 * Show the row as working (or not), with a status text.
	 *
	 * @param {HTMLElement} item Backup row.
	 * @param {string|null} text Status text, or null when done.
	 */
	const setBusy = ( item, text ) => {
		const label = item.querySelector( '.astra-reset-backup-text' );

		item.classList.toggle( 'is-busy', null !== text );
		item.setAttribute( 'aria-disabled', null !== text ? 'true' : 'false' );
		label.textContent = null !== text ? text : item.backupData.label;

		// A changed label on the focused row is not reliably read out, so announce it.
		if ( null !== text ) {
			wp.a11y.speak( text );
		}
	};

	/**
	 * Install and activate the Import / Export plugin, then switch the row to
	 * export. Uses the core install endpoint that `wp.updates.installPlugin()`
	 * uses, so no reload is needed.
	 *
	 * @param {HTMLElement} item Backup row.
	 * @return {Promise<boolean>} Whether the row can export now.
	 */
	const installAndActivate = async ( item ) => {
		const data = item.backupData;

		try {
			if ( 'install' === data.action ) {
				setBusy( item, data.busy );

				const installed = await ajax( {
					action: 'install-plugin',
					slug: data.slug,
					_ajax_nonce: data.installNonce,
				} );

				// Already there (installed in another tab) is fine: activate it.
				if ( ! installed.success && ! ( installed.data && 'folder_exists' === installed.data.errorCode ) ) {
					throw new Error( 'Install failed' );
				}

				setBusy( item, data.busyNext );
			} else {
				setBusy( item, data.busy );
			}

			const activated = await ajax( {
				action: 'astra_customizer_reset_activate_import_export',
				nonce: data.nonce,
			} );

			if ( ! activated.success ) {
				throw new Error( 'Activate failed' );
			}

			if ( ! activated.data ) {
				// The user can not export: nothing more to show.
				item.previousElementSibling.remove();
				item.remove();
				return false;
			}

			// Now an export row, so a failed export can be tried again from it.
			item.backupData = activated.data;
			item.querySelector( '.astra-reset-menu-item-help' ).textContent = activated.data.help;
			setBusy( item, null );
			return true;
		} catch ( error ) {
			setBusy( item, null );
			showBackupError( data.error );
			return false;
		}
	};

	/**
	 * Download the export file of the Import / Export plugin. Its export is a
	 * POST handled on `admin_init`, so this sends the same fields. Done with
	 * fetch, so a failure never leaves the Customizer and its changes.
	 *
	 * @param {HTMLElement} item Backup row.
	 * @return {Promise<boolean>} Whether the file was downloaded.
	 */
	const exportSettings = async ( item ) => {
		const data = item.backupData;

		setBusy( item, data.busy );

		try {
			const response = await fetch( data.postUrl, {
				method: 'POST',
				credentials: 'same-origin',
				body: new URLSearchParams( {
					astra_ie_action: 'export_settings',
					astra_export_nonce: data.nonce,
				} ),
			} );
			const type = response.headers.get( 'Content-Type' ) || '';

			// Anything else means the export did not run (for example an expired nonce).
			if ( ! response.ok || ! type.includes( 'application/json' ) ) {
				throw new Error( 'Export failed' );
			}

			const disposition = response.headers.get( 'Content-Disposition' ) || '';
			const match       = /filename="?([^";]+)"?/.exec( disposition );
			const url         = URL.createObjectURL( await response.blob() );
			const link        = el( 'a', { href: url, download: match ? match[ 1 ] : 'astra-settings-export.json' } );

			document.body.append( link );
			link.click();
			link.remove();
			setTimeout( () => URL.revokeObjectURL( url ), 1000 );

			api.notifications.add(
				new api.Notification( 'astra_customizer_reset_exported', {
					type: 'success',
					message: data.done,
					dismissible: true,
				} )
			);
			return true;
		} catch ( error ) {
			showBackupError( data.error );
			return false;
		} finally {
			setBusy( item, null );
		}
	};

	/**
	 * Handle a click on the backup row.
	 *
	 * @param {HTMLElement} item      Backup row.
	 * @param {Function}    closeMenu Closes the menu.
	 */
	const runBackup = async ( item, closeMenu ) => {
		if ( item.classList.contains( 'is-busy' ) ) {
			return;
		}

		if ( 'export' === item.backupData.action ) {
			if ( await exportSettings( item ) ) {
				closeMenu( true );
			}
			return;
		}

		// Installing or activating changes the site, so ask first.
		if ( ! confirmKeepMenu( item.backupData.confirm ) ) {
			item.focus();
			return;
		}

		// The menu stays open, so the row can show the progress. Then export
		// right away, so it is one click.
		item.focus();
		const ready = await installAndActivate( item );

		if ( ! item.isConnected ) {
			// The row was removed (the user can not export).
			closeMenu( true );
		} else if ( ready && await exportSettings( item ) ) {
			closeMenu( true );
		}
	};

	/**
	 * Build the button and menu, and wire up mouse and keyboard handling.
	 */
	const init = () => {
		const headerActions = document.getElementById( 'customize-header-actions' );
		const saveButton    = document.querySelector( '#customize-save-button-wrapper .save' );

		if ( ! headerActions ) {
			return;
		}

		const button = el(
			'button',
			{
				type: 'button',
				id: 'astra-reset',
				class: 'button astra-reset-toggle',
				'aria-haspopup': 'true',
				'aria-expanded': 'false',
				'aria-controls': 'astra-reset-menu',
			},
			[
				el( 'span', { class: 'astra-reset-toggle-label', text: l10n.stringReset } ),
				el( 'span', { class: 'dashicons dashicons-arrow-down-alt2', 'aria-hidden': 'true' } ),
			]
		);

		const menuItem = ( action, label, help, extraClass = '' ) =>
			el(
				'button',
				{
					type: 'button',
					role: 'menuitem',
					class: `astra-reset-menu-item ${ extraClass }`.trim(),
					tabindex: '-1',
					'data-action': action,
					'aria-labelledby': `astra-reset-label-${ action }`,
					'aria-describedby': `astra-reset-help-${ action }`,
				},
				[
					el( 'span', { class: 'astra-reset-menu-item-label', id: `astra-reset-label-${ action }`, text: label } ),
					el( 'span', { class: 'astra-reset-menu-item-help', id: `astra-reset-help-${ action }`, text: help } ),
				]
			);

		const separator = () => el( 'div', { class: 'astra-reset-menu-separator', role: 'separator' } );

		const menu = el(
			'div',
			{
				id: 'astra-reset-menu',
				class: 'astra-reset-menu',
				role: 'menu',
				'aria-label': l10n.stringMenuLabel,
			},
			[ menuItem( 'preview', l10n.stringPreview, l10n.stringPreviewHelp ) ]
		);
		menu.hidden = true;

		// Nudge to back up first, shown just before the reset that cannot be undone.
		const backup = l10n.backup;

		if ( backup ) {
			menu.append( separator(), backupItem( backup ) );
		}

		// The destructive option goes last, so it is the hardest one to pick by mistake.
		menu.append( separator(), menuItem( 'publish', l10n.stringPublish, l10n.stringPublishHelp, 'is-destructive' ) );

		// Read each time: the backup row can be removed after the plugin is activated.
		const getItems = () => [ ...menu.querySelectorAll( '[role="menuitem"]' ) ];

		const isOpen = () => 'true' === button.getAttribute( 'aria-expanded' );

		const openMenu = ( focusIndex = 0 ) => {
			menu.hidden = false;
			button.setAttribute( 'aria-expanded', 'true' );
			// A negative index counts from the end (-1 is the last item).
			const items = getItems();
			items[ ( focusIndex + items.length ) % items.length ].focus();
		};

		const closeMenu = ( returnFocus = false ) => {
			if ( ! isOpen() ) {
				return;
			}
			menu.hidden = true;
			button.setAttribute( 'aria-expanded', 'false' );
			if ( returnFocus ) {
				button.focus();
			}
		};

		button.addEventListener( 'click', ( event ) => {
			event.preventDefault();
			if ( isOpen() ) {
				closeMenu( true );
			} else {
				openMenu();
			}
		} );

		button.addEventListener( 'keydown', ( event ) => {
			if ( 'ArrowDown' === event.key ) {
				event.preventDefault();
				openMenu( 0 );
			} else if ( 'ArrowUp' === event.key ) {
				event.preventDefault();
				openMenu( -1 );
			}
		} );

		menu.addEventListener( 'keydown', ( event ) => {
			const items = getItems();
			const index = items.indexOf( document.activeElement );
			const focus = ( i ) => items[ ( i + items.length ) % items.length ].focus();

			switch ( event.key ) {
				case 'ArrowDown':
					event.preventDefault();
					focus( index + 1 );
					break;
				case 'ArrowUp':
					event.preventDefault();
					focus( index - 1 );
					break;
				case 'Home':
					event.preventDefault();
					focus( 0 );
					break;
				case 'End':
					event.preventDefault();
					focus( -1 );
					break;
				case 'Escape':
					event.preventDefault();
					closeMenu( true );
					break;
				case 'Tab':
					closeMenu();
					break;
			}
		} );

		// Close when clicking anywhere else, including the preview frame.
		document.addEventListener( 'mousedown', ( event ) => {
			if ( isOpen() && ! button.contains( event.target ) && ! menu.contains( event.target ) ) {
				closeMenu();
			}
		} );
		window.addEventListener( 'blur', () => {
			if ( ! confirming ) {
				closeMenu();
			}
		} );

		getItems().forEach( ( item ) =>
			item.addEventListener( 'click', ( event ) => {
				const { action } = item.dataset;

				if ( 'backup' === action ) {
					// The link (install screen) opens in a new tab by itself.
					if ( 'A' === item.tagName ) {
						closeMenu( true );
					} else {
						event.preventDefault();
						runBackup( item, closeMenu );
					}
					return;
				}

				event.preventDefault();
				closeMenu( true );

				if ( 'preview' === action ) {
					resetInPreview( button );
				} else if ( 'publish' === action && window.confirm( confirmText() ) ) {
					resetAndPublish( button );
				}
			} )
		);

		// The "Reset and Publish Now" confirm text, with a warning when a saved
		// draft or scheduled changeset is loaded: it is not removed by the reset.
		function confirmText() {
			const status = api.state( 'changesetStatus' ).get();

			if ( 'draft' === status || 'future' === status ) {
				return `${ l10n.stringConfirm }\n\n${ l10n.stringConfirmSaved }`;
			}

			return l10n.stringConfirm;
		}

		// A changeset that already holds a reset (restored autosave, draft,
		// scheduled or taken-over changeset) will reset on Publish too, so say so.
		if ( api.has( PENDING_ID ) && api( PENDING_ID ).get() ) {
			const [ stamp, keys = '' ] = api( PENDING_ID ).get().split( '|' );

			pendingStamp = stamp;
			watchStale( new Set( keys ? keys.split( ',' ) : [] ) );
			showPendingNotice();
		}

		// Once published the reset is saved, so the notice is no longer true. After
		// Save Draft or Schedule the reset is still pending, so the notice stays.
		let reloadedFor = '';

		api.bind( 'saved', ( response ) => {
			if ( response && 'publish' === response.changeset_status ) {
				api.notifications.remove( NOTICE );

				// Reload once to show the settings the server removed. Still dirty
				// means the reset was not part of this publish.
				const pending = api.has( PENDING_ID ) ? api( PENDING_ID ) : null;

				if ( pending && pending.get() && ! pending._dirty && pending.get() !== reloadedFor ) {
					reloadedFor = pending.get();
					reloadCustomizer();
				}
			}
		} );

		/*
		 * Insert before the Publish button in DOM order.
		 * LTR: float left  → visual [Reset All][Publish]
		 * RTL: float right → visual [Publish][Reset All], reading right-to-left: Reset All first.
		 */
		if ( saveButton ) {
			saveButton.before( button );
		} else {
			headerActions.append( button );
		}
		headerActions.append( menu );

		fitInHeader( headerActions, button );
	};

	/**
	 * Hide the "Reset All" text (the icon stays) when the header is too narrow
	 * for it, so the button does not slide under the icons at the other end of
	 * the header (close, Astra's tour). This happens with the narrowest sidebar,
	 * long translations, or when Publish changes to "Save Draft" or "Schedule".
	 * The text stays readable for screen readers.
	 *
	 * @param {HTMLElement}       headerActions The Customizer header.
	 * @param {HTMLButtonElement} button        The Reset All button.
	 */
	const fitInHeader = ( headerActions, button ) => {
		const GAP   = 4;
		const isRtl = 'rtl' === getComputedStyle( headerActions ).direction;
		let fullWidth = 0;

		const update = () => {
			if ( ! button.offsetWidth ) {
				return;
			}

			// Width with the text shown. Measured only then, so hiding it does not flip it back.
			if ( ! button.classList.contains( 'is-compact' ) ) {
				fullWidth = button.offsetWidth;
			}

			const rect = button.getBoundingClientRect();
			let fits   = true;

			// Other visible header items on the far side of the button.
			[ ...headerActions.children ].forEach( ( child ) => {
				if ( child.contains( button ) || child.classList.contains( 'spinner' ) || child.id === 'astra-reset-menu' || ! child.offsetWidth ) {
					return;
				}

				const other = child.getBoundingClientRect();

				if ( isRtl ) {
					// The button grows to the left in LTR, to the right in RTL.
					if ( other.left >= rect.left && rect.left + fullWidth + GAP > other.left ) {
						fits = false;
					}
				} else if ( other.right <= rect.right && rect.right - fullWidth - GAP < other.right ) {
					fits = false;
				}
			} );

			button.classList.toggle( 'is-compact', ! fits );
			button.title = fits ? '' : l10n.stringReset;
		};

		update();

		if ( 'ResizeObserver' in window ) {
			// Sidebar resizes and Publish button label changes.
			const observer = new ResizeObserver( update );
			observer.observe( headerActions );
			// The Publish button itself, not its wrapper: the wrapper also holds the
			// Reset All button, so watching it would loop when the button resizes.
			const publish = document.querySelector( '#customize-save-button-wrapper .save' );
			if ( publish ) {
				observer.observe( publish );
			}
		}
	};

	api.bind( 'ready', init );
} )( wp.customize );
