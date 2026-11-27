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
	 * Reset in Preview: nothing is saved until the user clicks Publish.
	 */
	const resetInPreview = () => {
		const defaults = l10n.defaults || {};

		api.each( ( setting ) => {
			// Notice boxes only show a message and hold no value.
			if ( ! setting.id.startsWith( PREFIX ) || setting.id.includes( 'ast-callback-notice' ) ) {
				return;
			}

			const key = setting.id.slice( PREFIX.length, -1 );

			// Settings without a known default are left as they are. Emptying them
			// breaks controls that expect an object (for example responsive spacing).
			if ( Object.prototype.hasOwnProperty.call( defaults, key ) ) {
				setting.set( clone( defaults[ key ] ) );
			}
		} );

		// Global color palette. Replace the whole value, like the full reset that
		// deletes the option, so custom colors and palette names are removed too.
		if ( api.has( 'astra-color-palettes' ) && l10n.paletteDefaults && l10n.paletteDefaults.palettes ) {
			api( 'astra-color-palettes' ).set( clone( l10n.paletteDefaults ) );
		}

		// Typography presets are removed by the full reset, so empty them here too.
		if ( api.has( 'astra-typography-presets' ) ) {
			api( 'astra-typography-presets' ).set( '' );
		}

		// The site logo is a theme mod and has no registered default.
		if ( api.has( 'custom_logo' ) ) {
			api( 'custom_logo' ).set( '' );
		}

		// Tell the server to also reset the Astra admin settings on Publish.
		// A new value each time, so it is sent again after an earlier reset.
		if ( api.has( PENDING_ID ) ) {
			api( PENDING_ID ).set( String( Date.now() ) );
		}

		showPendingNotice();
	};

	/**
	 * Reset and Publish Now: the original behaviour. Removes the settings in
	 * the database right away and reloads the Customizer.
	 *
	 * @param {HTMLButtonElement} button The Reset All button.
	 */
	const resetAndPublish = async ( button ) => {
		const spinner = document.querySelector( '#customize-header-actions .spinner' );

		if ( spinner ) {
			spinner.classList.add( 'is-active' );
		}
		button.disabled = true;

		try {
			const response = await fetch( window.ajaxurl, {
				method: 'POST',
				credentials: 'same-origin',
				body: new URLSearchParams( {
					wp_customize: 'on',
					action: 'astra_theme_customizer_reset',
					nonce: l10n.nonce,
				} ),
			} );
			const result = await response.json();

			if ( ! result || ! result.success ) {
				throw new Error( 'Reset failed' );
			}

			// Mark as saved so the "leave page" warning does not show, then reload.
			api.state( 'saved' ).set( true );
			window.location.href = window.location.href.split( '?' )[ 0 ];
		} catch ( error ) {
			if ( spinner ) {
				spinner.classList.remove( 'is-active' );
			}
			button.disabled = false;

			api.notifications.add(
				new api.Notification( 'astra_customizer_reset_error', {
					type: 'error',
					message: l10n.stringError,
					dismissible: true,
				} )
			);
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
			// Opens in a new tab so the Customizer and its changes stay open.
			const backupItem = el(
				'a',
				{
					href: backup.url,
					target: '_blank',
					rel: 'noopener noreferrer',
					role: 'menuitem',
					class: 'astra-reset-menu-item is-backup',
					tabindex: '-1',
					'data-action': 'backup',
					'aria-labelledby': 'astra-reset-label-backup',
					'aria-describedby': 'astra-reset-help-backup',
				},
				[
					el( 'span', { class: 'astra-reset-menu-item-label', id: 'astra-reset-label-backup' }, [
						el( 'span', { class: 'dashicons dashicons-download', 'aria-hidden': 'true' } ),
						backup.label,
						el( 'span', { class: 'dashicons dashicons-external', 'aria-hidden': 'true' } ),
						el( 'span', { class: 'screen-reader-text', text: ` ${ backup.newTab }` } ),
					] ),
					el( 'span', { class: 'astra-reset-menu-item-help', id: 'astra-reset-help-backup', text: backup.help } ),
				]
			);

			menu.append( separator(), backupItem );
		}

		// The destructive option goes last, so it is the hardest one to pick by mistake.
		menu.append( separator(), menuItem( 'publish', l10n.stringPublish, l10n.stringPublishHelp, 'is-destructive' ) );

		const items = [ ...menu.querySelectorAll( '[role="menuitem"]' ) ];

		const isOpen = () => 'true' === button.getAttribute( 'aria-expanded' );

		const openMenu = ( focusIndex = 0 ) => {
			menu.hidden = false;
			button.setAttribute( 'aria-expanded', 'true' );
			// A negative index counts from the end (-1 is the last item).
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
		window.addEventListener( 'blur', () => closeMenu() );

		items.forEach( ( item ) =>
			item.addEventListener( 'click', ( event ) => {
				const { action } = item.dataset;

				// The backup link opens in a new tab by itself.
				if ( 'backup' === action ) {
					closeMenu( true );
					return;
				}

				event.preventDefault();
				closeMenu( true );

				if ( 'preview' === action ) {
					resetInPreview();
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
			showPendingNotice();
		}

		// Once published the reset is saved, so the notice is no longer true. After
		// Save Draft or Schedule the reset is still pending, so the notice stays.
		api.bind( 'saved', ( response ) => {
			if ( response && 'publish' === response.changeset_status ) {
				api.notifications.remove( NOTICE );
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
			const publish = document.getElementById( 'customize-save-button-wrapper' );
			if ( publish ) {
				observer.observe( publish );
			}
		}
	};

	api.bind( 'ready', init );
} )( wp.customize );
