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
			if ( Object.hasOwn( defaults, key ) ) {
				setting.set( clone( defaults[ key ] ) );
			}
		} );

		// Global color palette. Keep the built-in presets, reset the palettes.
		if ( api.has( 'astra-color-palettes' ) && l10n.paletteDefaults?.palettes ) {
			api( 'astra-color-palettes' ).set( {
				...api( 'astra-color-palettes' ).get(),
				...clone( l10n.paletteDefaults ),
			} );
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

		api.notifications.add(
			new api.Notification( NOTICE, {
				type: 'info',
				message: l10n.stringNotice,
				dismissible: false,
			} )
		);
		wp.a11y.speak( l10n.stringNotice );
	};

	/**
	 * Reset and Publish Now: the original behaviour. Removes the settings in
	 * the database right away and reloads the Customizer.
	 *
	 * @param {HTMLButtonElement} button The Reset All button.
	 */
	const resetAndPublish = async ( button ) => {
		document.querySelector( '#customize-header-actions .spinner' )?.classList.add( 'is-active' );
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

			// Mark as saved so the "leave page" warning does not show.
			if ( 'pass' === result?.data ) {
				api.state( 'saved' ).set( true );
			}
		} finally {
			window.location.href = window.location.href.split( '?' )[ 0 ];
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
					'aria-describedby': `astra-reset-help-${ action }`,
				},
				[
					el( 'span', { class: 'astra-reset-menu-item-label', text: label } ),
					el( 'span', { class: 'astra-reset-menu-item-help', id: `astra-reset-help-${ action }`, text: help } ),
				]
			);

		const menu = el(
			'div',
			{
				id: 'astra-reset-menu',
				class: 'astra-reset-menu',
				role: 'menu',
				'aria-label': l10n.stringMenuLabel,
			},
			[
				menuItem( 'preview', l10n.stringPreview, l10n.stringPreviewHelp ),
				el( 'div', { class: 'astra-reset-menu-separator', role: 'separator' } ),
				menuItem( 'publish', l10n.stringPublish, l10n.stringPublishHelp, 'is-destructive' ),
			]
		);
		menu.hidden = true;

		// Nudge to back up first, shown at the end of the menu.
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
					'aria-describedby': 'astra-reset-help-backup',
				},
				[
					el( 'span', { class: 'astra-reset-menu-item-label' }, [
						el( 'span', { class: 'dashicons dashicons-download', 'aria-hidden': 'true' } ),
						backup.label,
						el( 'span', { class: 'dashicons dashicons-external', 'aria-hidden': 'true' } ),
						el( 'span', { class: 'screen-reader-text', text: backup.newTab } ),
					] ),
					el( 'span', { class: 'astra-reset-menu-item-help', id: 'astra-reset-help-backup', text: backup.help } ),
				]
			);

			menu.append( el( 'div', { class: 'astra-reset-menu-separator', role: 'separator' } ), backupItem );
		}

		const items = [ ...menu.querySelectorAll( '[role="menuitem"]' ) ];

		const isOpen = () => 'true' === button.getAttribute( 'aria-expanded' );

		const openMenu = ( focusIndex = 0 ) => {
			menu.hidden = false;
			button.setAttribute( 'aria-expanded', 'true' );
			items.at( focusIndex )?.focus();
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
			const focus = ( i ) => items.at( i % items.length )?.focus();

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
				} else if ( 'publish' === action && window.confirm( l10n.stringConfirm ) ) {
					resetAndPublish( button );
				}
			} )
		);

		// Once published (or the changes are discarded) the notice is no longer true.
		api.state( 'saved' ).bind( ( isSaved ) => {
			if ( isSaved ) {
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
	};

	api.bind( 'ready', init );
} )( wp.customize );
