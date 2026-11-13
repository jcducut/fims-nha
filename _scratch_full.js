
	document.addEventListener('DOMContentLoaded', function() {
		const
			assignmentsByUser = <?php echo $assignmentsByUserJson ?: '{}'; ?> ;
		const
			permissionsByLevel = <?php echo $permissionsByLevelJson ?: '{}'; ?> ;
		const
			allModules = <?php echo $allModulesJson ?: '[]'; ?> ;
		const
			usersById = <?php echo $usersByIdJson ?: '{}'; ?> ;
		const
			userLevelsById = <?php echo $userLevelsByIdJson ?: '{}'; ?> ;
		const
			deleteAssignmentBaseUrl = <?php echo json_encode($deleteAssignmentBaseUrl); ?> ;
		const
			canManagePermissions = <?php echo $canManagePermissions ? 'true' : 'false'; ?> ;

		// ── Searchable select (combobox) ──
		function initSearchableSelect(wrap) {
			const input = wrap.querySelector('input[type="text"]');
			const hidden = wrap.querySelector('input[type="hidden"]');
			const menu = wrap.querySelector('.searchable-select-menu');
			const noResults = menu.querySelector('.searchable-select-option.is-empty');
			const options = Array.from(menu.querySelectorAll('.searchable-select-option:not(.is-empty)'));
			const placeholder = wrap.getAttribute('data-placeholder') || '';

			function labelFor(value) {
				const opt = options.find(function(o) {
					return o.dataset.value === value;
				});
				return opt ? opt.dataset.label : placeholder;
			}

			function filterOptions(query) {
				const q = query.trim().toLowerCase();
				let anyVisible = false;
				options.forEach(function(o) {
					const match = q === '' || o.dataset.label.toLowerCase().indexOf(q) !== -1;
					o.style.display = match ? '' : 'none';
					o.classList.toggle('is-selected', o.dataset.value === hidden.value);
					if (match) anyVisible = true;
				});
				noResults.style.display = anyVisible ? 'none' : '';
			}

			function openMenu() {
				menu.style.display = 'block';
				filterOptions(input.value === labelFor(hidden.value) ? '' : input.value);
				input.select();
				wrap.scrollIntoView({
					block: 'nearest'
				});
			}

			function closeMenu() {
				menu.style.display = 'none';
				input.value = labelFor(hidden.value);
			}

			input.addEventListener('focus', openMenu);
			input.addEventListener('input', function() {
				filterOptions(input.value);
				menu.style.display = 'block';
			});
			input.addEventListener('keydown', function(e) {
				if (e.key === 'Escape') input.blur();
			});
			input.addEventListener('blur', function() {
				setTimeout(closeMenu, 150);
			});

			options.forEach(function(o) {
				o.addEventListener('mousedown', function(e) {
					e.preventDefault();
					hidden.value = o.dataset.value;
					hidden.dispatchEvent(new Event('change', {
						bubbles: true
					}));
					input.value = o.dataset.label || placeholder;
					menu.style.display = 'none';
				});
			});

			wrap.resetSearchableSelect = function() {
				hidden.value = '';
				input.value = placeholder;
			};
			wrap.setSearchableSelectValue = function(value) {
				hidden.value = value || '';
				input.value = labelFor(hidden.value);
			};

			input.value = labelFor(hidden.value);
		}

		document.querySelectorAll('[data-searchable-select]').forEach(initSearchableSelect);

		function escapeHtml(str) {
			return String(str)
				.replace(/&/g, '&amp;')
				.replace(/</g, '&lt;')
				.replace(/>/g, '&gt;')
				.replace(/"/g, '&quot;')
				.replace(/'/g, '&#39;');
		}

		// ── User search / filter + pagination ──
		// Rows are hidden (not removed) so each row's data-user-id-bearing buttons
		// stay intact regardless of which page is currently shown.
		const searchInput = document.getElementById('userSearchInput');
		const USER_PAGE_SIZE = 7;
		let userCurrentPage = 1;
		const userPaginationInfo = document.getElementById('userPaginationInfo');
		const userPrevPageBtn = document.getElementById('userPrevPageBtn');
		const userNextPageBtn = document.getElementById('userNextPageBtn');
		const userPageNumbers = document.getElementById('userPageNumbers');

		function applyUserTableView() {
			const allRows = Array.from(document.querySelectorAll('#usersList .app-user-row'));
			const term = searchInput ? searchInput.value.toLowerCase() : '';
			const matched = allRows.filter(function(row) {
				return !term || row.textContent.toLowerCase().includes(term);
			});

			const totalPages = Math.max(1, Math.ceil(matched.length / USER_PAGE_SIZE));
			if (userCurrentPage > totalPages) userCurrentPage = totalPages;
			if (userCurrentPage < 1) userCurrentPage = 1;

			const start = (userCurrentPage - 1) * USER_PAGE_SIZE;
			const end = start + USER_PAGE_SIZE;

			allRows.forEach(function(row) {
				row.style.display = 'none';
			});
			matched.forEach(function(row, idx) {
				row.style.display = (idx >= start && idx < end) ? '' : 'none';
			});

			if (userPaginationInfo) {
				userPaginationInfo.textContent = matched.length === 0 ?
					'No users to show.' :
					'Showing ' + (start + 1) + '–' + Math.min(end, matched.length) + ' of ' + matched.length;
			}
			if (userPrevPageBtn) userPrevPageBtn.disabled = userCurrentPage <= 1;
			if (userNextPageBtn) userNextPageBtn.disabled = userCurrentPage >= totalPages;
			if (userPageNumbers) {
				userPageNumbers.innerHTML = Array.from({
						length: totalPages
					}, function(_, i) {
						return i + 1;
					})
					.map(function(p) {
						return '<button type="button" class="btn app-table-page-btn' +
							(p === userCurrentPage ? ' is-active' : '') +
							' user-page-btn" data-page="' + p + '">' + p + '</button>';
					}).join('');
			}
		}

		if (searchInput) {
			searchInput.addEventListener('input', function() {
				userCurrentPage = 1;
				applyUserTableView();
			});
		}
		if (userPrevPageBtn) {
			userPrevPageBtn.addEventListener('click', function() {
				userCurrentPage--;
				applyUserTableView();
			});
		}
		if (userNextPageBtn) {
			userNextPageBtn.addEventListener('click', function() {
				userCurrentPage++;
				applyUserTableView();
			});
		}
		if (userPageNumbers) {
			userPageNumbers.addEventListener('click', function(e) {
				const btn = e.target.closest('.user-page-btn');
				if (!btn) return;
				userCurrentPage = parseInt(btn.dataset.page, 10) || 1;
				applyUserTableView();
			});
		}

		applyUserTableView();

		// ── Role & access assignment panel ──
		const selectedUserLabel = document.getElementById('selectedUserLabel');
		const currentAssignmentsList = document.getElementById('currentAssignmentsList');
		const assignUserId = document.getElementById('assignUserId');
		const assignSystemId = document.getElementById('assignSystemId');
		const assignLevelSelect = document.getElementById('assignLevelSelect');
		const assignAccessBtn = document.getElementById('assignAccessBtn');
		const assignRoleList = document.getElementById('assignRoleList');

		function syncRoleListActive(levelId) {
			if (!assignRoleList) return;
			assignRoleList.querySelectorAll('.app-role-list-item').forEach(function(item) {
				item.classList.toggle('is-active', item.dataset.levelId === String(levelId || ''));
			});
		}
		if (assignRoleList) {
			assignRoleList.querySelectorAll('.app-role-list-item').forEach(function(item) {
				item.addEventListener('click', function() {
					if (!assignLevelSelect) return;
					assignLevelSelect.value = this.dataset.levelId;
					assignLevelSelect.dispatchEvent(new Event('change'));
				});
			});
			// Edit icon lives inside each role row now (not a single header button
			// tied to the current selection), so it needs its own click handling
			// and must not also trigger the row's own "select this role" click.
			assignRoleList.querySelectorAll('.app-role-list-item-edit').forEach(function(btn) {
				btn.addEventListener('click', function(e) {
					e.stopPropagation();
					openEditUserLevelModal(this.dataset.levelId);
				});
			});
		}

		const roleSearchInput = document.getElementById('roleSearchInput');
		const roleListEmptyMsg = document.getElementById('roleListEmptyMsg');

		function applyRoleListFilter() {
			if (!assignRoleList) return;
			const term = roleSearchInput ? roleSearchInput.value.toLowerCase().trim() : '';
			let visibleCount = 0;
			assignRoleList.querySelectorAll('.app-role-list-item').forEach(function(item) {
				const matches = !term || item.textContent.toLowerCase().includes(term);
				item.style.display = matches ? '' : 'none';
				if (matches) visibleCount++;
			});
			if (roleListEmptyMsg) roleListEmptyMsg.style.display = visibleCount === 0 ? '' : 'none';
		}
		if (roleSearchInput) {
			roleSearchInput.addEventListener('input', applyRoleListFilter);
		}
		const permissionMatrixBody = document.getElementById('permissionMatrixBody');
		const savePermissionsBtn = document.getElementById('savePermissionsBtn');

		const PERM_KEYS = ['list', 'view', 'add', 'edit', 'delete', 'search', 'export', 'lookup'];
		const permSelectAll = document.getElementById('permSelectAll');
		const permissionSearchInput = document.getElementById('permissionSearchInput');
		const checkAllPermissionsBtn = document.getElementById('checkAllPermissionsBtn');
		const uncheckAllPermissionsBtn = document.getElementById('uncheckAllPermissionsBtn');
		const syncPermissionModulesBtn = document.getElementById('syncPermissionModulesBtn');
		const showHiddenModulesCheck = document.getElementById('showHiddenModulesCheck');

		function rowCheckboxes(tr) {
			return PERM_KEYS.map(function(key) {
				return tr.querySelector('.perm-' + key);
			});
		}

		function syncRowCheckbox(tr) {
			const boxes = rowCheckboxes(tr);
			const rowToggle = tr.querySelector('.perm-row-toggle');
			if (rowToggle) rowToggle.checked = boxes.every(function(b) {
				return b.checked;
			});
		}

		function syncColumnCheckboxes() {
			PERM_KEYS.forEach(function(key) {
				const colToggle = document.querySelector('.perm-col-toggle[data-perm="' + key + '"]');
				if (!colToggle) return;
				const boxes = Array.from(permissionMatrixBody.querySelectorAll('.perm-' + key));
				colToggle.checked = boxes.length > 0 && boxes.every(function(b) {
					return b.checked;
				});
			});
		}

		function syncMasterCheckbox() {
			if (!permSelectAll) return;
			const boxes = Array.from(permissionMatrixBody.querySelectorAll('input[type="checkbox"].perm-cell'));
			permSelectAll.checked = boxes.length > 0 && boxes.every(function(b) {
				return b.checked;
			});
		}

		function syncAllToggles() {
			document.querySelectorAll('#permissionMatrixBody tr[data-table]').forEach(syncRowCheckbox);
			syncColumnCheckboxes();
			syncMasterCheckbox();
		}

		// ── Permission matrix: pagination ──
		// Rows are never removed from the DOM (only hidden) so Save Permissions, which
		// reads every tr[data-table] regardless of visibility, keeps working across pages.
		const PERMISSION_PAGE_SIZE = 10;
		let permissionCurrentPage = 1;
		const permissionPaginationInfo = document.getElementById('permissionPaginationInfo');
		const permissionPrevPageBtn = document.getElementById('permissionPrevPageBtn');
		const permissionNextPageBtn = document.getElementById('permissionNextPageBtn');
		const permissionPageNumbers = document.getElementById('permissionPageNumbers');

		function applyPermissionTableView() {
			if (!permissionMatrixBody) return;
			const term = permissionSearchInput ? permissionSearchInput.value.toLowerCase() : '';
			const allRows = Array.from(permissionMatrixBody.querySelectorAll('tr[data-table]'));
			const matched = allRows.filter(function(row) {
				return !term || row.textContent.toLowerCase().includes(term);
			});

			const totalPages = Math.max(1, Math.ceil(matched.length / PERMISSION_PAGE_SIZE));
			if (permissionCurrentPage > totalPages) permissionCurrentPage = totalPages;
			if (permissionCurrentPage < 1) permissionCurrentPage = 1;

			const start = (permissionCurrentPage - 1) * PERMISSION_PAGE_SIZE;
			const end = start + PERMISSION_PAGE_SIZE;

			allRows.forEach(function(row) {
				row.style.display = 'none';
			});
			matched.forEach(function(row, idx) {
				row.style.display = (idx >= start && idx < end) ? '' : 'none';
			});

			if (permissionPaginationInfo) {
				permissionPaginationInfo.textContent = matched.length === 0 ?
					'No modules to show.' :
					'Showing ' + (start + 1) + '–' + Math.min(end, matched.length) + ' of ' + matched.length;
			}
			if (permissionPrevPageBtn) permissionPrevPageBtn.disabled = permissionCurrentPage <= 1;
			if (permissionNextPageBtn) permissionNextPageBtn.disabled = permissionCurrentPage >= totalPages;
			if (permissionPageNumbers) {
				permissionPageNumbers.innerHTML = Array.from({
						length: totalPages
					}, function(_, i) {
						return i + 1;
					})
					.map(function(p) {
						return '<button type="button" class="btn app-table-page-btn' +
							(p === permissionCurrentPage ? ' is-active' : '') +
							' permission-page-btn" data-page="' + p + '">' + p + '</button>';
					}).join('');
			}
		}

		if (permissionPrevPageBtn) {
			permissionPrevPageBtn.addEventListener('click', function() {
				permissionCurrentPage--;
				applyPermissionTableView();
			});
		}
		if (permissionNextPageBtn) {
			permissionNextPageBtn.addEventListener('click', function() {
				permissionCurrentPage++;
				applyPermissionTableView();
			});
		}
		if (permissionPageNumbers) {
			permissionPageNumbers.addEventListener('click', function(e) {
				const btn = e.target.closest('.permission-page-btn');
				if (!btn) return;
				permissionCurrentPage = parseInt(btn.dataset.page, 10) || 1;
				applyPermissionTableView();
			});
		}

		function renderPermissionMatrix(levelId) {
			if (!permissionMatrixBody) return;
			if (savePermissionsBtn) savePermissionsBtn.disabled = !levelId;
			if (checkAllPermissionsBtn) checkAllPermissionsBtn.disabled = !levelId;
			if (uncheckAllPermissionsBtn) uncheckAllPermissionsBtn.disabled = !levelId;

			const permissionNoRoleNotice = document.getElementById('permissionNoRoleNotice');
			if (permissionNoRoleNotice) permissionNoRoleNotice.style.display = levelId ? 'none' : '';

			if (allModules.length === 0) {
				permissionMatrixBody.innerHTML =
					'<tr><td colspan="11" class="text-muted">No modules to show.</td></tr>';
				if (permissionPaginationInfo) permissionPaginationInfo.textContent = '';
				if (permissionPageNumbers) permissionPageNumbers.innerHTML = '';
				return;
			}

			// The module list itself (rename / remove / restore) is always manageable -
			// only the per-level permission checkboxes need an actual level selected.
			const existing = {};
			if (levelId) {
				(permissionsByLevel[levelId] || []).forEach(function(r) {
					existing[r.table_name] = r;
				});
			}
			const canEditCells = canManagePermissions && !!levelId;

			const checkbox = function(perm, checked) {
				return '<input type="checkbox" class="perm-cell perm-' + perm + '" ' + (checked ? 'checked' :
						'') +
					(canEditCells ? '' : ' disabled') + '>';
			};

			const showHidden = showHiddenModulesCheck && showHiddenModulesCheck.checked;
			const visibleModules = allModules.filter(function(m) {
				return showHidden || !m.is_hidden;
			});

			if (visibleModules.length === 0) {
				permissionMatrixBody.innerHTML =
					'<tr><td colspan="11" class="text-muted">No modules to show.</td></tr>';
				if (permissionPaginationInfo) permissionPaginationInfo.textContent = '';
				if (permissionPageNumbers) permissionPageNumbers.innerHTML = '';
				return;
			}

			permissionMatrixBody.innerHTML = visibleModules.map(function(module) {
				const r = existing[module.table_name] || {};
				const cells = PERM_KEYS.map(function(key) {
					return '<td class="text-center">' + checkbox(key, !!r[key]) + '</td>';
				}).join('');
				let actionsCell = '';
				if (canManagePermissions) {
					let actions =
						'<button type="button" class="btn btn-link btn-sm p-0 edit-module-caption-btn" data-table="' +
						escapeHtml(module.table_name) + '" data-caption="' + escapeHtml(module.caption) +
						'" title="Rename">' +
						'<i class="fas fa-pencil-alt"></i></button>';
					if (module.is_hidden && !module.is_default_hidden) {
						actions +=
							'<button type="button" class="btn btn-link btn-sm p-0 ml-1 text-success restore-module-btn" data-table="' +
							escapeHtml(module.table_name) + '" title="Restore to the list">' +
							'<i class="fas fa-undo"></i></button>';
					} else if (!module.is_hidden) {
						actions +=
							'<button type="button" class="btn btn-link btn-sm p-0 ml-1 text-danger hide-module-btn" data-table="' +
							escapeHtml(module.table_name) + '" title="Hide from the list">' +
							'<i class="fas fa-eye-slash"></i></button>';
					}
					actionsCell = '<td class="text-center app-table-permissions-divider-left">' + actions +
						'</td>';
				}
				const rowClass = module.is_hidden ? ' class="text-muted"' : '';
				return '<tr' + rowClass + ' data-table="' + escapeHtml(module.table_name) +
					'" data-full-key="' + escapeHtml(module.full_key) + '">' +
					'<td><input type="checkbox" class="perm-row-toggle" title="Toggle every permission for this page"' +
					(canEditCells ? '' : ' disabled') + '></td>' +
					'<td class="app-table-permissions-divider">' + escapeHtml(module.caption) + (module
						.is_hidden ? ' <span class="badge badge-secondary">Hidden</span>' : '') + '</td>' +
					cells + actionsCell + '</tr>';
			}).join('');

			syncAllToggles();

			permissionCurrentPage = 1;
			applyPermissionTableView();
		}

		if (permissionMatrixBody) {
			permissionMatrixBody.addEventListener('change', function(e) {
				if (e.target.classList.contains('perm-cell')) {
					syncRowCheckbox(e.target.closest('tr'));
					syncColumnCheckboxes();
					syncMasterCheckbox();
				} else if (e.target.classList.contains('perm-row-toggle')) {
					const tr = e.target.closest('tr');
					rowCheckboxes(tr).forEach(function(b) {
						b.checked = e.target.checked;
					});
					syncColumnCheckboxes();
					syncMasterCheckbox();
				}
			});

			permissionMatrixBody.addEventListener('click', function(e) {
				const editBtn = e.target.closest('.edit-module-caption-btn');
				if (editBtn) {
					openRenameModuleModal(editBtn.dataset.table, editBtn.dataset.caption);
					return;
				}
				const hideBtn = e.target.closest('.hide-module-btn');
				if (hideBtn) {
					const module = allModules.find(function(m) {
						return m.table_name === hideBtn.dataset.table;
					});
					setModuleVisibility(hideBtn.dataset.table, module ? module.caption : hideBtn.dataset
						.table, true);
					return;
				}
				const restoreBtn = e.target.closest('.restore-module-btn');
				if (restoreBtn) {
					const module = allModules.find(function(m) {
						return m.table_name === restoreBtn.dataset.table;
					});
					setModuleVisibility(restoreBtn.dataset.table, module ? module.caption : restoreBtn
						.dataset.table, false);
				}
			});
		}

		if (showHiddenModulesCheck) {
			showHiddenModulesCheck.addEventListener('change', function() {
				renderPermissionMatrix(assignLevelSelect ? assignLevelSelect.value : '');
			});
		}

		function setModuleVisibility(tableName, caption, isHidden) {
			return fetch('/api/user-access/module-visibility', {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-Authorization': 'Bearer ' + ew.API_JWT_TOKEN
					},
					body: JSON.stringify({
						table_name: tableName,
						caption: caption,
						is_hidden: isHidden
					})
				})
				.then(function(r) {
					return r.json();
				})
				.then(function(result) {
					if (result.success) {
						ew.showToast(isHidden ? 'Module removed from the list' : 'Module restored', 'success');
						location.reload();
					} else {
						ew.alert(result.message || 'Error updating module visibility');
					}
				})
				.catch(function() {
					ew.alert('Error connecting to the server. Please try again.');
				});
		}

		document.querySelectorAll('.perm-col-toggle').forEach(function(colToggle) {
			colToggle.addEventListener('change', function() {
				const key = this.dataset.perm;
				document.querySelectorAll('#permissionMatrixBody .perm-' + key).forEach(function(
				b) {
					b.checked = colToggle.checked;
				});
				document.querySelectorAll('#permissionMatrixBody tr[data-table]').forEach(
					syncRowCheckbox);
				syncMasterCheckbox();
			});
		});

		if (permSelectAll) {
			permSelectAll.addEventListener('change', function() {
				document.querySelectorAll('#permissionMatrixBody input[type="checkbox"]').forEach(function(
					b) {
					b.checked = permSelectAll.checked;
				});
				syncColumnCheckboxes();
			});
		}

		if (permissionSearchInput) {
			permissionSearchInput.addEventListener('input', function() {
				permissionCurrentPage = 1;
				applyPermissionTableView();
			});
		}

		function setAllPermissionCheckboxes(checked) {
			document.querySelectorAll('#permissionMatrixBody input[type="checkbox"]').forEach(function(b) {
				b.checked = checked;
			});
			syncAllToggles();
		}

		if (checkAllPermissionsBtn) {
			checkAllPermissionsBtn.addEventListener('click', function() {
				setAllPermissionCheckboxes(true);
			});
		}
		if (uncheckAllPermissionsBtn) {
			uncheckAllPermissionsBtn.addEventListener('click', function() {
				setAllPermissionCheckboxes(false);
			});
		}

		if (syncPermissionModulesBtn) {
			syncPermissionModulesBtn.addEventListener('click', function() {
				syncPermissionModulesBtn.disabled = true;
				fetch('/api/user-access/sync-modules', {
						method: 'POST',
						headers: {
							'Content-Type': 'application/json',
							'X-Authorization': 'Bearer ' + ew.API_JWT_TOKEN
						}
					})
					.then(function(r) {
						return r.json();
					})
					.then(function(result) {
						if (result.success) {
							ew.showToast(result.message || 'Sync complete', 'success');
							if (result.inserted > 0) location.reload();
						} else {
							ew.alert(result.message || 'Error syncing permission modules');
						}
					})
					.catch(function() {
						ew.alert('Error connecting to the server. Please try again.');
					})
					.finally(function() {
						syncPermissionModulesBtn.disabled = false;
					});
			});
		}

		if (savePermissionsBtn) {
			savePermissionsBtn.addEventListener('click', function() {
				const levelId = assignLevelSelect ? assignLevelSelect.value : '';
				if (!levelId) {
					ew.alert('Select a user level first.');
					return;
				}
				const permissions = Array.from(permissionMatrixBody.querySelectorAll('tr[data-table]'))
					.map(function(tr) {
						const entry = {
							table_name: tr.dataset.table,
							full_key: tr.dataset.fullKey
						};
						PERM_KEYS.forEach(function(key) {
							entry[key] = tr.querySelector('.perm-' + key).checked;
						});
						return entry;
					});

				savePermissionsBtn.disabled = true;
				fetch('/api/user-access/permissions', {
						method: 'POST',
						headers: {
							'Content-Type': 'application/json',
							'X-Authorization': 'Bearer ' + ew.API_JWT_TOKEN
						},
						body: JSON.stringify({
							user_level_id: levelId,
							permissions: permissions
						})
					})
					.then(function(r) {
						return r.json();
					})
					.then(function(result) {
						if (result.success) {
							// Merge instead of replace: hidden rows aren't in this payload, and dropping
							// them from the cache would show them unticked under "Show hidden".
							const savedTables = {};
							permissions.forEach(function(p) { savedTables[p.table_name] = true; });
							const untouched = (permissionsByLevel[levelId] || []).filter(function(r) {
								return !savedTables[r.table_name];
							});
							permissionsByLevel[levelId] = untouched.concat(permissions);
							ew.showToast('Permissions saved successfully', 'success');
						} else {
							ew.alert(result.message || 'Error saving permissions');
						}
					})
					.catch(function() {
						ew.alert('Error connecting to the server. Please try again.');
					})
					.finally(function() {
						savePermissionsBtn.disabled = false;
					});
			});
		}

		function selectUserForAccess(userId, username) {
			if (selectedUserLabel) selectedUserLabel.textContent = username + ' (ID: ' + userId + ')';
			if (assignUserId) assignUserId.value = userId;

			const assignments = assignmentsByUser[userId] || [];
			if (currentAssignmentsList) {
				currentAssignmentsList.innerHTML = assignments.length ?
					assignments.map(function(a) {
						return '<div class="app-role-access-list-item">' +
							'<span><strong>' + a.system_name + '</strong>: ' + a.level_name + '</span>' +
							'<a href="' + deleteAssignmentBaseUrl + '/' + a.assignment_id +
							'" class="app-table-action-btn" title="Remove">' +
							'<i class="fas fa-trash"></i> <span class="app-table-action-btn-label">Remove</span></a></div>';
					}).join('') :
					'<p class="text-muted small mb-0">No access assigned yet.</p>';
			}

			// If this user already has exactly one assigned role, select it so its
			// permissions are immediately visible/editable instead of resetting blank.
			// (assignLevelSelect.value silently falls back to '' if the id has no
			// matching <option> - e.g. a stale/deleted role - so re-read it after
			// assigning rather than trusting the original value.)
			if (assignLevelSelect) {
				assignLevelSelect.value = assignments.length === 1 ? String(assignments[0].user_level_id) : '';
			}
			const levelId = assignLevelSelect ? assignLevelSelect.value : '';
			const selectedOption = levelId ? assignLevelSelect.options[assignLevelSelect.selectedIndex] : null;
			if (assignSystemId) assignSystemId.value = selectedOption ? selectedOption.dataset.systemId : '';
			if (assignAccessBtn) assignAccessBtn.disabled = !levelId;
			syncRoleListActive(levelId);
			renderPermissionMatrix(levelId);

			const panel = document.getElementById('accessPanel');
			if (panel) panel.scrollIntoView({
				behavior: 'smooth',
				block: 'start'
			});
		}

		document.querySelectorAll('.manage-access-btn').forEach(function(btn) {
			btn.addEventListener('click', function() {
				selectUserForAccess(this.dataset.userId, this.dataset.username);
			});
		});

		// ── Role Details panel (fills the space under "Assign Access" that
		// opened up once the role list stopped stretching to match the taller
		// permissions column) ──
		const roleDetailsEmpty = document.getElementById('roleDetailsEmpty');
		const roleDetailsContent = document.getElementById('roleDetailsContent');
		const roleDetailsSystem = document.getElementById('roleDetailsSystem');
		const roleDetailsDescription = document.getElementById('roleDetailsDescription');
		const roleDetailsUserCount = document.getElementById('roleDetailsUserCount');

		function countUsersForLevel(levelId) {
			let count = 0;
			Object.keys(assignmentsByUser).forEach(function(userId) {
				const hasLevel = (assignmentsByUser[userId] || []).some(function(a) {
					return String(a.user_level_id) === String(levelId);
				});
				if (hasLevel) count++;
			});
			return count;
		}

		function renderRoleDetails(levelId) {
			if (!roleDetailsEmpty || !roleDetailsContent) return;
			const level = levelId ? userLevelsById[levelId] : null;
			if (!level) {
				roleDetailsEmpty.style.display = '';
				roleDetailsContent.style.display = 'none';
				return;
			}
			roleDetailsEmpty.style.display = 'none';
			roleDetailsContent.style.display = '';
			if (roleDetailsSystem) roleDetailsSystem.textContent = level.system_name || 'N/A';
			if (roleDetailsDescription) roleDetailsDescription.textContent = level.description || '—';
			if (roleDetailsUserCount) {
				const n = countUsersForLevel(levelId);
				roleDetailsUserCount.textContent = n === 1 ? '1 user' : (n + ' users');
			}
		}

		if (assignLevelSelect) {
			assignLevelSelect.addEventListener('change', function() {
				const selectedOption = this.options[this.selectedIndex];
				assignAccessBtn.disabled = !this.value;
				if (assignSystemId) assignSystemId.value = this.value ? selectedOption.dataset.systemId :
					'';
				syncRoleListActive(this.value);
				renderPermissionMatrix(this.value);
				renderRoleDetails(this.value);
			});
		}

		if (assignAccessBtn) {
			assignAccessBtn.addEventListener('click', function() {
				const userId = assignUserId ? assignUserId.value : '';
				const userLevelId = assignLevelSelect ? assignLevelSelect.value : '';
				if (!userId) {
					ew.alert('Select a user first.');
					return;
				}
				if (!userLevelId) {
					ew.alert('Select a user level first.');
					return;
				}

				assignAccessBtn.disabled = true;
				fetch('/api/user-access/assignments', {
						method: 'POST',
						headers: {
							'Content-Type': 'application/json',
							'X-Authorization': 'Bearer ' + ew.API_JWT_TOKEN
						},
						body: JSON.stringify({
							user_id: userId,
							user_level_id: userLevelId,
							system_id: assignSystemId ? assignSystemId.value : null
						})
					})
					.then(function(r) {
						return r.json();
					})
					.then(function(result) {
						if (result.success) {
							ew.showToast('Access assigned successfully', 'success');
							location.reload();
						} else {
							ew.alert(result.message || 'Error assigning access');
							assignAccessBtn.disabled = false;
						}
					})
					.catch(function() {
						ew.alert('Error connecting to the server. Please try again.');
						assignAccessBtn.disabled = false;
					});
			});
		}

		// ── Add User modal ──
		const addUserOverlay = document.getElementById('addUserOverlay');
		const addUserForm = document.getElementById('addUserForm');
		const addUserError = document.getElementById('addUserError');
		const openAddUserModalBtn = document.getElementById('openAddUserModalBtn');

		// ── Add User modal: step navigation ──
		const addUserSteps = Array.from(addUserForm.querySelectorAll('.add-user-step'));
		const addUserStepperSegs = Array.from(addUserForm.querySelectorAll('.add-user-stepper-seg'));
		const addUserStepperLabels = Array.from(addUserForm.querySelectorAll('.add-user-stepper-labels span'));
		const addUserBackBtn = document.getElementById('addUserBackBtn');
		const addUserNextBtn = document.getElementById('addUserNextBtn');
		const submitAddUserBtn = document.getElementById('submitAddUserBtn');
		let addUserStepIndex = 0;

		function validateAddUserStep(stepEl) {
			const fields = Array.from(stepEl.querySelectorAll('input, select, textarea'));
			for (const field of fields) {
				if (!field.checkValidity()) {
					field.reportValidity();
					return false;
				}
			}
			return true;
		}

		function showAddUserStep(index) {
			addUserStepIndex = index;
			addUserSteps.forEach(function(stepEl, i) {
				stepEl.style.display = i === index ? '' : 'none';
				stepEl.classList.toggle('is-visible', i === index);
			});
			addUserStepperSegs.forEach(function(seg, i) {
				seg.classList.toggle('is-active', i === index);
				seg.classList.toggle('is-done', i < index);
			});
			addUserStepperLabels.forEach(function(label, i) {
				label.classList.toggle('is-active', i === index);
				label.classList.toggle('is-done', i < index);
			});
			if (addUserBackBtn) addUserBackBtn.style.display = index === 0 ? 'none' : '';
			const isLastStep = index === addUserSteps.length - 1;
			if (addUserNextBtn) addUserNextBtn.style.display = isLastStep ? 'none' : '';
			if (submitAddUserBtn) submitAddUserBtn.style.display = isLastStep ? '' : 'none';
		}

		if (addUserNextBtn) {
			addUserNextBtn.addEventListener('click', function() {
				if (!validateAddUserStep(addUserSteps[addUserStepIndex])) return;
				showAddUserStep(addUserStepIndex + 1);
			});
		}
		if (addUserBackBtn) {
			addUserBackBtn.addEventListener('click', function() {
				showAddUserStep(addUserStepIndex - 1);
			});
		}
		addUserForm.addEventListener('keydown', function(e) {
			if (e.key === 'Enter' && e.target.tagName === 'INPUT' && addUserStepIndex < addUserSteps
				.length - 1) {
				e.preventDefault();
				if (addUserNextBtn) addUserNextBtn.click();
			}
		});

		function openAddUserModal() {
			if (!addUserOverlay) return;
			addUserForm.reset();
			addUserForm.querySelectorAll('[data-searchable-select]').forEach(function(wrap) {
				if (wrap.resetSearchableSelect) wrap.resetSearchableSelect();
			});
			addUserError.style.display = 'none';
			showAddUserStep(0);
			addUserOverlay.style.display = 'flex';
		}

		function closeAddUserModal() {
			if (addUserOverlay) addUserOverlay.style.display = 'none';
		}

		if (openAddUserModalBtn) openAddUserModalBtn.addEventListener('click', openAddUserModal);
		const closeAddUserModalBtn = document.getElementById('closeAddUserModalBtn');
		const cancelAddUserBtn = document.getElementById('cancelAddUserBtn');
		if (closeAddUserModalBtn) closeAddUserModalBtn.addEventListener('click', closeAddUserModal);
		if (cancelAddUserBtn) cancelAddUserBtn.addEventListener('click', closeAddUserModal);

		const toggleAddUserPassword = document.getElementById('toggleAddUserPassword');
		if (toggleAddUserPassword) {
			toggleAddUserPassword.addEventListener('click', function() {
				const field = document.getElementById('addUserPassword');
				const icon = document.getElementById('toggleAddUserPasswordIcon');
				if (field.type === 'password') {
					field.type = 'text';
					icon.classList.replace('fa-eye', 'fa-eye-slash');
				} else {
					field.type = 'password';
					icon.classList.replace('fa-eye-slash', 'fa-eye');
				}
			});
		}

		if (addUserForm) {
			addUserForm.addEventListener('submit', function(e) {
				e.preventDefault();
				addUserError.style.display = 'none';

				const payload = {
					username: document.getElementById('addUserUsername').value.trim(),
					email: document.getElementById('addUserEmail').value.trim(),
					employee_id: document.getElementById('addUserEmployeeId').value.trim() || null,
					password: document.getElementById('addUserPassword').value,
					first_name: document.getElementById('addUserFirstName').value.trim(),
					middle_name: document.getElementById('addUserMiddleName').value.trim() || null,
					last_name: document.getElementById('addUserLastName').value.trim(),
					suffix: document.getElementById('addUserSuffix').value || null,
					mobile_number: document.getElementById('addUserMobileNumber').value.trim() || null,
					rc_id: document.getElementById('addUserRcId').value || null,
					position_id: document.getElementById('addUserPosition').value || null,
					is_active: document.getElementById('addUserIsActive').checked
				};

				const submitBtn = document.getElementById('submitAddUserBtn');
				submitBtn.disabled = true;

				fetch('/api/user-access/users', {
						method: 'POST',
						headers: {
							'Content-Type': 'application/json',
							'X-Authorization': 'Bearer ' + ew.API_JWT_TOKEN
						},
						body: JSON.stringify(payload)
					})
					.then(function(r) {
						return r.json();
					})
					.then(function(result) {
						if (result.success) {
							ew.showToast('User created successfully', 'success');
							closeAddUserModal();
							location.reload();
						} else {
							addUserError.textContent = result.message || 'Error creating user';
							addUserError.style.display = 'block';
						}
					})
					.catch(function() {
						addUserError.textContent = 'Error connecting to the server. Please try again.';
						addUserError.style.display = 'block';
					})
					.finally(function() {
						submitBtn.disabled = false;
					});
			});
		}

		// ── Create User Level modal ──
		const createUserLevelOverlay = document.getElementById('createUserLevelOverlay');
		const createUserLevelForm = document.getElementById('createUserLevelForm');
		const createUserLevelError = document.getElementById('createUserLevelError');
		const openCreateUserLevelModalBtn = document.getElementById('openCreateUserLevelModalBtn');

		function openCreateUserLevelModal() {
			if (!createUserLevelOverlay) return;
			createUserLevelForm.reset();
			createUserLevelError.style.display = 'none';
			createUserLevelOverlay.style.display = 'block';
		}

		function closeCreateUserLevelModal() {
			if (createUserLevelOverlay) createUserLevelOverlay.style.display = 'none';
		}

		if (openCreateUserLevelModalBtn) openCreateUserLevelModalBtn.addEventListener('click',
			openCreateUserLevelModal);
		const closeCreateUserLevelModalBtn = document.getElementById('closeCreateUserLevelModalBtn');
		const cancelCreateUserLevelBtn = document.getElementById('cancelCreateUserLevelBtn');
		if (closeCreateUserLevelModalBtn) closeCreateUserLevelModalBtn.addEventListener('click',
			closeCreateUserLevelModal);
		if (cancelCreateUserLevelBtn) cancelCreateUserLevelBtn.addEventListener('click',
		closeCreateUserLevelModal);

		if (createUserLevelForm) {
			createUserLevelForm.addEventListener('submit', function(e) {
				e.preventDefault();
				createUserLevelError.style.display = 'none';

				const payload = {
					name: document.getElementById('createUserLevelName').value.trim(),
					description: document.getElementById('createUserLevelDescription').value.trim() ||
						null,
					system_id: document.getElementById('createUserLevelSystem').value || null
				};

				const submitBtn = document.getElementById('submitCreateUserLevelBtn');
				submitBtn.disabled = true;

				fetch('/api/user-access/user-levels', {
						method: 'POST',
						headers: {
							'Content-Type': 'application/json',
							'X-Authorization': 'Bearer ' + ew.API_JWT_TOKEN
						},
						body: JSON.stringify(payload)
					})
					.then(function(r) {
						return r.json();
					})
					.then(function(result) {
						if (result.success) {
							ew.showToast('User level created successfully', 'success');
							closeCreateUserLevelModal();
							location.reload();
						} else {
							createUserLevelError.textContent = result.message ||
								'Error creating user level';
							createUserLevelError.style.display = 'block';
						}
					})
					.catch(function() {
						createUserLevelError.textContent =
							'Error connecting to the server. Please try again.';
						createUserLevelError.style.display = 'block';
					})
					.finally(function() {
						submitBtn.disabled = false;
					});
			});
		}

		// ── Edit User Level modal ──
		const editUserLevelOverlay = document.getElementById('editUserLevelOverlay');
		const editUserLevelForm = document.getElementById('editUserLevelForm');
		const editUserLevelError = document.getElementById('editUserLevelError');

		function openEditUserLevelModal(levelId) {
			if (!editUserLevelOverlay) return;
			const level = userLevelsById[levelId];
			if (!level) return;

			editUserLevelForm.reset();
			editUserLevelError.style.display = 'none';
			document.getElementById('editUserLevelId').value = levelId;
			document.getElementById('editUserLevelName').value = level.name || '';
			document.getElementById('editUserLevelDescription').value = level.description || '';
			document.getElementById('editUserLevelSystem').value = level.system_id || '';

			editUserLevelOverlay.style.display = 'block';
		}

		function closeEditUserLevelModal() {
			if (editUserLevelOverlay) editUserLevelOverlay.style.display = 'none';
		}

		const closeEditUserLevelModalBtn = document.getElementById('closeEditUserLevelModalBtn');
		const cancelEditUserLevelBtn = document.getElementById('cancelEditUserLevelBtn');
		if (closeEditUserLevelModalBtn) closeEditUserLevelModalBtn.addEventListener('click',
			closeEditUserLevelModal);
		if (cancelEditUserLevelBtn) cancelEditUserLevelBtn.addEventListener('click', closeEditUserLevelModal);

		if (editUserLevelForm) {
			editUserLevelForm.addEventListener('submit', function(e) {
				e.preventDefault();
				editUserLevelError.style.display = 'none';

				const levelId = document.getElementById('editUserLevelId').value;
				const payload = {
					name: document.getElementById('editUserLevelName').value.trim(),
					description: document.getElementById('editUserLevelDescription').value.trim() ||
						null,
					system_id: document.getElementById('editUserLevelSystem').value || null
				};

				const submitBtn = document.getElementById('submitEditUserLevelBtn');
				submitBtn.disabled = true;

				fetch('/api/user-access/user-levels/' + levelId, {
						method: 'PUT',
						headers: {
							'Content-Type': 'application/json',
							'X-Authorization': 'Bearer ' + ew.API_JWT_TOKEN
						},
						body: JSON.stringify(payload)
					})
					.then(function(r) {
						return r.json();
					})
					.then(function(result) {
						if (result.success) {
							ew.showToast('User level updated successfully', 'success');
							closeEditUserLevelModal();
							location.reload();
						} else {
							editUserLevelError.textContent = result.message ||
								'Error updating user level';
							editUserLevelError.style.display = 'block';
						}
					})
					.catch(function() {
						editUserLevelError.textContent =
							'Error connecting to the server. Please try again.';
						editUserLevelError.style.display = 'block';
					})
					.finally(function() {
						submitBtn.disabled = false;
					});
			});
		}

		// ── Edit User modal ──
		const editUserOverlay = document.getElementById('editUserOverlay');
		const editUserForm = document.getElementById('editUserForm');
		const editUserError = document.getElementById('editUserError');

		// ── Edit User modal: step navigation ──
		const editUserSteps = Array.from(editUserForm.querySelectorAll('.edit-user-step'));
		const editUserStepperSegs = Array.from(editUserForm.querySelectorAll('.add-user-stepper-seg'));
		const editUserStepperLabels = Array.from(editUserForm.querySelectorAll('.add-user-stepper-labels span'));
		const editUserBackBtn = document.getElementById('editUserBackBtn');
		const editUserNextBtn = document.getElementById('editUserNextBtn');
		const submitEditUserBtn = document.getElementById('submitEditUserBtn');
		let editUserStepIndex = 0;

		function validateEditUserStep(stepEl) {
			const fields = Array.from(stepEl.querySelectorAll('input, select, textarea'));
			for (const field of fields) {
				if (!field.checkValidity()) {
					field.reportValidity();
					return false;
				}
			}
			return true;
		}

		function showEditUserStep(index) {
			editUserStepIndex = index;
			editUserSteps.forEach(function(stepEl, i) {
				stepEl.style.display = i === index ? '' : 'none';
				stepEl.classList.toggle('is-visible', i === index);
			});
			editUserStepperSegs.forEach(function(seg, i) {
				seg.classList.toggle('is-active', i === index);
				seg.classList.toggle('is-done', i < index);
			});
			editUserStepperLabels.forEach(function(label, i) {
				label.classList.toggle('is-active', i === index);
				label.classList.toggle('is-done', i < index);
			});
			if (editUserBackBtn) editUserBackBtn.style.display = index === 0 ? 'none' : '';
			const isLastStep = index === editUserSteps.length - 1;
			if (editUserNextBtn) editUserNextBtn.style.display = isLastStep ? 'none' : '';
			if (submitEditUserBtn) submitEditUserBtn.style.display = isLastStep ? '' : 'none';
		}

		if (editUserNextBtn) {
			editUserNextBtn.addEventListener('click', function() {
				if (!validateEditUserStep(editUserSteps[editUserStepIndex])) return;
				showEditUserStep(editUserStepIndex + 1);
			});
		}
		if (editUserBackBtn) {
			editUserBackBtn.addEventListener('click', function() {
				showEditUserStep(editUserStepIndex - 1);
			});
		}
		editUserForm.addEventListener('keydown', function(e) {
			if (e.key === 'Enter' && e.target.tagName === 'INPUT' && editUserStepIndex < editUserSteps
				.length - 1) {
				e.preventDefault();
				if (editUserNextBtn) editUserNextBtn.click();
			}
		});

		function openEditUserModal(userId) {
			if (!editUserOverlay) return;
			const user = usersById[userId];
			if (!user) return;

			editUserForm.reset();
			editUserError.style.display = 'none';
			document.getElementById('editUserId').value = userId;
			document.getElementById('editUserUsername').value = user.username || '';
			document.getElementById('editUserEmail').value = user.email || '';
			document.getElementById('editUserEmployeeId').value = user.employee_id || '';
			document.getElementById('editUserFirstName').value = user.first_name || '';
			document.getElementById('editUserMiddleName').value = user.middle_name || '';
			document.getElementById('editUserLastName').value = user.last_name || '';
			document.getElementById('editUserSuffix').value = user.suffix || '';
			document.getElementById('editUserMobileNumber').value = user.mobile_number || '';
			const editUserRcIdWrap = document.getElementById('editUserRcId').closest('[data-searchable-select]');
			if (editUserRcIdWrap && editUserRcIdWrap.setSearchableSelectValue) {
				editUserRcIdWrap.setSearchableSelectValue(user.rc_id || '');
			}
			document.getElementById('editUserPosition').value = user.position_id || '';
			document.getElementById('editUserIsActive').checked = !!user.is_active;

			showEditUserStep(0);
			editUserOverlay.style.display = 'flex';
		}

		function closeEditUserModal() {
			if (editUserOverlay) editUserOverlay.style.display = 'none';
		}

		document.querySelectorAll('.edit-user-btn').forEach(function(btn) {
			btn.addEventListener('click', function() {
				openEditUserModal(this.dataset.userId);
			});
		});

		const closeEditUserModalBtn = document.getElementById('closeEditUserModalBtn');
		const cancelEditUserBtn = document.getElementById('cancelEditUserBtn');
		if (closeEditUserModalBtn) closeEditUserModalBtn.addEventListener('click', closeEditUserModal);
		if (cancelEditUserBtn) cancelEditUserBtn.addEventListener('click', closeEditUserModal);

		const toggleEditUserPassword = document.getElementById('toggleEditUserPassword');
		if (toggleEditUserPassword) {
			toggleEditUserPassword.addEventListener('click', function() {
				const field = document.getElementById('editUserPassword');
				const icon = document.getElementById('toggleEditUserPasswordIcon');
				if (field.type === 'password') {
					field.type = 'text';
					icon.classList.replace('fa-eye', 'fa-eye-slash');
				} else {
					field.type = 'password';
					icon.classList.replace('fa-eye-slash', 'fa-eye');
				}
			});
		}

		if (editUserForm) {
			editUserForm.addEventListener('submit', function(e) {
				e.preventDefault();
				editUserError.style.display = 'none';

				const userId = document.getElementById('editUserId').value;
				const payload = {
					username: document.getElementById('editUserUsername').value.trim(),
					email: document.getElementById('editUserEmail').value.trim(),
					employee_id: document.getElementById('editUserEmployeeId').value.trim() || null,
					first_name: document.getElementById('editUserFirstName').value.trim(),
					middle_name: document.getElementById('editUserMiddleName').value.trim() || null,
					last_name: document.getElementById('editUserLastName').value.trim(),
					suffix: document.getElementById('editUserSuffix').value || null,
					mobile_number: document.getElementById('editUserMobileNumber').value.trim() ||
						null,
					rc_id: document.getElementById('editUserRcId').value || null,
					position_id: document.getElementById('editUserPosition').value || null,
					is_active: document.getElementById('editUserIsActive').checked
				};
				const password = document.getElementById('editUserPassword').value;
				if (password) {
					payload.password = password;
				}

				const submitBtn = document.getElementById('submitEditUserBtn');
				submitBtn.disabled = true;

				fetch('/api/user-access/users/' + userId, {
						method: 'PUT',
						headers: {
							'Content-Type': 'application/json',
							'X-Authorization': 'Bearer ' + ew.API_JWT_TOKEN
						},
						body: JSON.stringify(payload)
					})
					.then(function(r) {
						return r.json();
					})
					.then(function(result) {
						if (result.success) {
							ew.showToast('User updated successfully', 'success');
							closeEditUserModal();
							location.reload();
						} else {
							editUserError.textContent = result.message || 'Error updating user';
							editUserError.style.display = 'block';
						}
					})
					.catch(function() {
						editUserError.textContent =
						'Error connecting to the server. Please try again.';
						editUserError.style.display = 'block';
					})
					.finally(function() {
						submitBtn.disabled = false;
					});
			});
		}

		// ── Rename Module modal ──
		const renameModuleOverlay = document.getElementById('renameModuleOverlay');
		const renameModuleForm = document.getElementById('renameModuleForm');
		const renameModuleError = document.getElementById('renameModuleError');

		function openRenameModuleModal(tableName, currentCaption) {
			if (!renameModuleOverlay) return;
			renameModuleForm.reset();
			renameModuleError.style.display = 'none';
			document.getElementById('renameModuleTableName').value = tableName;
			document.getElementById('renameModuleCaption').value = currentCaption || '';
			renameModuleOverlay.style.display = 'block';
		}

		function closeRenameModuleModal() {
			if (renameModuleOverlay) renameModuleOverlay.style.display = 'none';
		}

		const closeRenameModuleModalBtn = document.getElementById('closeRenameModuleModalBtn');
		const cancelRenameModuleBtn = document.getElementById('cancelRenameModuleBtn');
		if (closeRenameModuleModalBtn) closeRenameModuleModalBtn.addEventListener('click', closeRenameModuleModal);
		if (cancelRenameModuleBtn) cancelRenameModuleBtn.addEventListener('click', closeRenameModuleModal);

		if (renameModuleForm) {
			renameModuleForm.addEventListener('submit', function(e) {
				e.preventDefault();
				renameModuleError.style.display = 'none';

				const payload = {
					table_name: document.getElementById('renameModuleTableName').value,
					caption: document.getElementById('renameModuleCaption').value.trim()
				};

				const submitBtn = document.getElementById('submitRenameModuleBtn');
				submitBtn.disabled = true;

				fetch('/api/user-access/module-caption', {
						method: 'POST',
						headers: {
							'Content-Type': 'application/json',
							'X-Authorization': 'Bearer ' + ew.API_JWT_TOKEN
						},
						body: JSON.stringify(payload)
					})
					.then(function(r) {
						return r.json();
					})
					.then(function(result) {
						if (result.success) {
							ew.showToast('Module renamed successfully', 'success');
							closeRenameModuleModal();
							location.reload();
						} else {
							renameModuleError.textContent = result.message || 'Error renaming module';
							renameModuleError.style.display = 'block';
						}
					})
					.catch(function() {
						renameModuleError.textContent =
							'Error connecting to the server. Please try again.';
						renameModuleError.style.display = 'block';
					})
					.finally(function() {
						submitBtn.disabled = false;
					});
			});
		}

		// Show the full module list (with rename/remove actions) immediately, even
		// before a user level is selected - only the per-level checkboxes need a level.
		renderPermissionMatrix('');
	});
