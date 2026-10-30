( function () {
	'use strict';

	const { __, _n, sprintf } = wp.i18n;
	const config = window.blockUsageFinder;
	const byId = ( id ) => document.getElementById( id );
	const OPEN_LIMIT = 5;

	let blocks = {};
	let scanned = 0;
	let stopped = false;
	let stopRequested = false;

	function make( tag, className, text ) {
		const node = document.createElement( tag );

		if ( className ) {
			node.className = className;
		}

		if ( undefined !== text ) {
			node.textContent = text;
		}

		return node;
	}

	function showError( message ) {
		const notice = make( 'div', 'notice notice-error' );

		notice.appendChild( make( 'p', '', message ) );
		byId( 'bu-notice' ).replaceChildren( notice );
	}

	async function requestBatch( postType, statuses, after ) {
		const body = new FormData();

		body.append( 'action', 'block_usage_scan' );
		body.append( 'nonce', config.nonce );
		body.append( 'scan_type', postType );
		body.append( 'after', after );
		statuses.forEach( ( status ) => body.append( 'statuses[]', status ) );

		const response = await fetch( config.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body,
		} );
		const json = await response.json();

		if ( ! response.ok || ! json.success ) {
			throw new Error(
				sprintf(
					/* translators: %d: HTTP status code. */
					__( 'The scan request failed (HTTP %d).', 'block-usage-finder' ),
					response.status
				)
			);
		}

		return json.data;
	}

	function merge( data ) {
		scanned += data.scanned;

		Object.entries( data.blocks ).forEach( ( [ name, posts ] ) => {
			if ( ! blocks[ name ] ) {
				blocks[ name ] = {
					name,
					registered: !! data.registered[ name ],
					uses: 0,
					posts: [],
				};
			}

			posts.forEach( ( post ) => {
				blocks[ name ].uses += post.count;
				blocks[ name ].posts.push( post );
			} );
		} );
	}

	function sorted( list ) {
		const mode = byId( 'bu-sort' ).value;

		return list.sort( ( a, b ) => {
			if ( 'posts' === mode && a.posts.length !== b.posts.length ) {
				return b.posts.length - a.posts.length;
			}

			if ( 'uses' === mode && a.uses !== b.uses ) {
				return b.uses - a.uses;
			}

			return a.name.localeCompare( b.name );
		} );
	}

	function visibleBlocks() {
		const term = byId( 'bu-filter' ).value.trim().toLowerCase();
		const onlyMissing = byId( 'bu-missing' ).checked;
		const withCore = byId( 'bu-core' ).checked;

		return sorted(
			Object.values( blocks ).filter(
				( block ) =>
					( withCore || ! block.name.startsWith( 'core/' ) ) &&
					( ! onlyMissing || ! block.registered ) &&
					( ! term || block.name.toLowerCase().includes( term ) )
			)
		);
	}

	function namespaceOf( name ) {
		return name.split( '/' )[ 0 ];
	}

	function groupedBlocks( list ) {
		const groups = {};

		list.forEach( ( block ) => {
			const key = namespaceOf( block.name );

			groups[ key ] = groups[ key ] || [];
			groups[ key ].push( block );
		} );

		return Object.keys( groups )
			.sort( ( a, b ) => a.localeCompare( b ) )
			.map( ( key ) => ( { namespace: key, blocks: groups[ key ] } ) );
	}

	function postItem( post ) {
		const item = make( 'li' );
		const title = post.title || __( '(no title)', 'block-usage-finder' );
		const type = config.postTypes[ post.type ] || post.type;

		if ( post.link ) {
			const link = make( 'a', '', title );

			link.href = post.link;
			item.appendChild( link );
		} else {
			item.appendChild( document.createTextNode( title ) );
		}

		item.appendChild(
			make(
				'span',
				'description',
				' ' + sprintf( '#%1$d, %2$s, %3$s, x%4$d', post.id, type, post.status, post.count )
			)
		);

		return item;
	}

	function blockRow( block ) {
		const row = make( 'tr' );
		const name = make( 'td' );
		const registered = make( 'td' );
		const used = make( 'td' );
		const details = make( 'details' );
		const list = make( 'ul' );

		name.appendChild( make( 'code', '', block.name ) );

		if ( block.registered ) {
			registered.textContent = __( 'Yes', 'block-usage-finder' );
		} else {
			registered.appendChild(
				make( 'strong', 'block-usage__missing', __( 'Missing', 'block-usage-finder' ) )
			);
		}

		details.open = block.posts.length <= OPEN_LIMIT;
		details.appendChild(
			make(
				'summary',
				'',
				sprintf(
					/* translators: %d: number of posts. */
					_n( '%d post', '%d posts', block.posts.length, 'block-usage-finder' ),
					block.posts.length
				)
			)
		);
		block.posts.forEach( ( post ) => list.appendChild( postItem( post ) ) );
		details.appendChild( list );
		used.appendChild( details );

		row.append( name, registered, used );

		return row;
	}

	function groupRow( group ) {
		const row = make( 'tr', 'block-usage__group' );
		const cell = make(
			'th',
			'',
			sprintf(
				/* translators: 1: block namespace, 2: number of blocks. */
				_n( '%1$s (%2$d block)', '%1$s (%2$d blocks)', group.blocks.length, 'block-usage-finder' ),
				group.namespace,
				group.blocks.length
			)
		);

		cell.colSpan = 3;
		row.appendChild( cell );

		return row;
	}

	function render( finished ) {
		const list = visibleBlocks();
		const rows = byId( 'bu-rows' );
		const missing = list.filter( ( block ) => ! block.registered ).length;
		const uses = list.reduce( ( sum, block ) => sum + block.uses, 0 );
		const fragment = document.createDocumentFragment();
		let summary = sprintf(
			/* translators: %d: number of posts. */
			_n(
				'%d post with blocks scanned.',
				'%d posts with blocks scanned.',
				scanned,
				'block-usage-finder'
			),
			scanned
		);

		if ( byId( 'bu-group' ).checked ) {
			groupedBlocks( list ).forEach( ( group ) => {
				fragment.appendChild( groupRow( group ) );
				group.blocks.forEach( ( block ) => fragment.appendChild( blockRow( block ) ) );
			} );
		} else {
			list.forEach( ( block ) => fragment.appendChild( blockRow( block ) ) );
		}

		if ( 0 === list.length ) {
			const row = make( 'tr' );
			const cell = make( 'td', '', __( 'No blocks found.', 'block-usage-finder' ) );

			cell.colSpan = 3;
			row.appendChild( cell );
			fragment.appendChild( row );
		}

		if ( finished && stopped ) {
			summary += ' ' + __( 'The scan was stopped before it finished.', 'block-usage-finder' );
		}

		rows.replaceChildren( fragment );
		byId( 'bu-summary' ).textContent = summary;
		byId( 'bu-totals' ).textContent = sprintf(
			/* translators: 1: number of blocks, 2: number not registered, 3: total uses. */
			__( 'Blocks: %1$d. Not registered: %2$d. Total uses: %3$d.', 'block-usage-finder' ),
			list.length,
			missing,
			uses
		);
		byId( 'bu-results' ).hidden = false;
	}

	function csvCell( value ) {
		let text = String( value );

		if ( /^[=+\-@\t\r]/.test( text ) ) {
			text = "'" + text;
		}

		return /[",\r\n]/.test( text ) ? '"' + text.replace( /"/g, '""' ) + '"' : text;
	}

	function exportCsv() {
		const lines = [
			[ 'Block', 'Registered', 'Post type', 'Post ID', 'Title', 'Status', 'Uses', 'Edit URL' ],
		];

		visibleBlocks()
			.sort( ( a, b ) =>
				byId( 'bu-group' ).checked
					? namespaceOf( a.name ).localeCompare( namespaceOf( b.name ) )
					: 0
			)
			.forEach( ( block ) => {
				block.posts.forEach( ( post ) => {
					lines.push( [
						block.name,
						block.registered ? 'yes' : 'no',
						post.type,
						post.id,
						post.title,
						post.status,
						post.count,
						post.link,
					] );
				} );
			} );

		const csv = '﻿' + lines.map( ( line ) => line.map( csvCell ).join( ',' ) ).join( '\r\n' );
		const url = URL.createObjectURL( new Blob( [ csv ], { type: 'text/csv;charset=utf-8' } ) );
		const link = make( 'a' );

		link.href = url;
		link.download = 'block-usage-' + new Date().toISOString().slice( 0, 10 ) + '.csv';
		document.body.appendChild( link );
		link.click();
		link.remove();
		URL.revokeObjectURL( url );
	}

	function setRunning( running ) {
		byId( 'bu-scan' ).disabled = running;
		byId( 'bu-stop' ).hidden = ! running;
		byId( 'bu-progress' ).hidden = ! running;
	}

	async function scan( event ) {
		event.preventDefault();

		const selected = byId( 'bu-post-type' ).value;
		const types = '__all' === selected ? Object.keys( config.postTypes ) : [ selected ];
		const statuses = Array.from(
			document.querySelectorAll( '#bu-form input[name="statuses[]"]:checked' )
		).map( ( input ) => input.value );

		byId( 'bu-notice' ).replaceChildren();

		if ( 0 === statuses.length ) {
			showError( __( 'Select at least one post status.', 'block-usage-finder' ) );
			return;
		}

		blocks = {};
		scanned = 0;
		stopped = false;
		stopRequested = false;
		byId( 'bu-results' ).hidden = true;
		setRunning( true );

		try {
			for ( const type of types ) {
				let after = 0;
				let done = false;

				while ( ! done && ! stopRequested ) {
					byId( 'bu-status' ).textContent = sprintf(
						/* translators: 1: post type label, 2: number of posts scanned so far. */
						__( 'Scanning %1$s. %2$d posts with blocks found so far.', 'block-usage-finder' ),
						config.postTypes[ type ],
						scanned
					);

					const data = await requestBatch( type, statuses, after );

					merge( data );
					after = data.last;
					done = data.done;
				}

				if ( stopRequested ) {
					break;
				}
			}

			stopped = stopRequested;
			render( true );
		} catch ( error ) {
			showError( error.message );
			stopped = true;

			if ( scanned > 0 ) {
				render( true );
			}
		}

		setRunning( false );
	}

	byId( 'bu-form' ).addEventListener( 'submit', scan );
	byId( 'bu-stop' ).addEventListener( 'click', () => {
		stopRequested = true;
	} );
	byId( 'bu-export' ).addEventListener( 'click', exportCsv );

	[ 'bu-filter', 'bu-sort', 'bu-missing', 'bu-core', 'bu-group' ].forEach( ( id ) => {
		const refresh = () => {
			if ( ! byId( 'bu-results' ).hidden ) {
				render( true );
			}
		};

		byId( id ).addEventListener( 'input', refresh );
		byId( id ).addEventListener( 'change', refresh );
	} );
}() );
