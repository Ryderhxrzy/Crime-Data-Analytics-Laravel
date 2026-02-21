class I{constructor(){this.map=null,this.markers=[],this.incidents=[],this.filteredIncidents=[],this.currentPage=1,this.pageSize=10,this.filters={search:"",category:"",status:"",barangay:"",date:"",clearance:""};try{this.initializeElements(),this.initializeEventListeners(),this.loadInitialData(),this.initializeRealtimeListeners()}catch(e){console.error("Error initializing CrimePageManager:",e)}}initializeElements(){const e=document.getElementById("searchInput"),t=document.getElementById("tableSearchInput"),a=document.getElementById("categoryFilter"),s=document.getElementById("statusFilter"),n=document.getElementById("barangayFilter"),d=document.getElementById("dateFilter"),o=document.getElementById("pageSize"),i=document.getElementById("tablePageSize"),c=document.getElementById("caseStatusFilter"),p=document.getElementById("clearanceStatusFilter"),g=document.getElementById("addIncidentBtn"),m=document.getElementById("exportBtn"),u=document.getElementById("closeModal"),l=document.getElementById("incidentModal"),b=document.getElementById("addIncidentModal"),y=document.getElementById("closeAddModal"),v=document.getElementById("cancelAddIncident"),h=document.getElementById("addIncidentForm");window.crimePage={searchInput:e,tableSearchInput:t,categoryFilter:a,statusFilter:s,barangayFilter:n,dateFilter:d,pageSizeSelect:o,tablePageSizeSelect:i,caseStatusFilter:c,clearanceStatusFilter:p,addIncidentBtn:g,exportBtn:m,closeModalBtn:u,modalOverlay:l,addIncidentModal:b,closeAddModalBtn:y,cancelAddIncidentBtn:v,addIncidentForm:h}}initializeMap(){this.map=window.L.map("crimeMap").setView([14.676,121.0437],11),window.L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png",{attribution:"© OpenStreetMap contributors",maxZoom:19}).addTo(this.map),window.L.Control.geocoder({defaultMarkGeocode:!1,placeholder:"Search location...",errorMessage:"Nothing found.",showResultIcons:!0,suggestMinLength:2,suggestTimeout:250,queryMinLength:1}).addTo(this.map)}initializeEventListeners(){var t,a,s,n,d,o,i,c,p,g,m,u,l,b,y,v,h;const e=window.crimePage;(t=e.searchInput)==null||t.addEventListener("input",r=>{this.filters.search=r.target.value,this.applyFilters()}),(a=e.tableSearchInput)==null||a.addEventListener("input",r=>{this.filters.search=r.target.value,this.applyFilters()}),(s=e.categoryFilter)==null||s.addEventListener("change",r=>{this.filters.category=r.target.value,this.applyFilters()}),(n=e.statusFilter)==null||n.addEventListener("change",r=>{this.filters.status=r.target.value,this.applyFilters()}),(d=e.barangayFilter)==null||d.addEventListener("change",r=>{this.filters.barangay=r.target.value,this.applyFilters()}),(o=e.dateFilter)==null||o.addEventListener("change",r=>{this.filters.date=r.target.value,this.applyFilters()}),(i=e.caseStatusFilter)==null||i.addEventListener("change",r=>{this.filters.status=r.target.value,this.applyFilters()}),(c=e.clearanceStatusFilter)==null||c.addEventListener("change",r=>{this.filters.clearance=r.target.value,this.applyFilters()}),(p=e.tablePageSizeSelect)==null||p.addEventListener("change",r=>{this.pageSize=parseInt(r.target.value),this.currentPage=1,this.renderTable()}),(g=e.addIncidentBtn)==null||g.addEventListener("click",()=>{this.showAddIncidentModal()}),(m=e.exportBtn)==null||m.addEventListener("click",()=>{this.exportData()}),(u=e.closeModalBtn)==null||u.addEventListener("click",()=>{this.closeModal()}),(l=e.modalOverlay)==null||l.addEventListener("click",r=>{r.target===e.modalOverlay&&this.closeModal()}),(b=e.closeAddModalBtn)==null||b.addEventListener("click",()=>{this.closeAddIncidentModal()}),(y=e.cancelAddIncidentBtn)==null||y.addEventListener("click",()=>{this.closeAddIncidentModal()}),(v=e.addIncidentForm)==null||v.addEventListener("submit",r=>{r.preventDefault(),this.submitIncidentForm()}),(h=e.addIncidentModal)==null||h.addEventListener("click",r=>{r.target===e.addIncidentModal&&this.closeAddIncidentModal()}),this.setupSidebarToggle(),this.setupTableCheckboxListeners()}setupTableCheckboxListeners(){const e=document.getElementById("selectAllCheckbox"),t=document.getElementById("crimesTableBody");e&&e.addEventListener("change",n=>{const d=n.target.checked,o=t==null?void 0:t.querySelectorAll('input[type="checkbox"][data-incident-id]');o&&o.forEach(i=>{i.checked=d}),console.log(d?`Selected all ${(o==null?void 0:o.length)||0} incidents`:"Deselected all incidents")}),document.querySelectorAll(".see-more-button").forEach(n=>{n.addEventListener("click",d=>{d.stopPropagation();const o=n.getAttribute("data-target"),i=n.getAttribute("data-incident-id");this.expandColumn(i,o)})}),document.querySelectorAll(".read-more-btn").forEach(n=>{n.addEventListener("click",d=>{d.stopPropagation();const o=n.getAttribute("data-target"),i=document.getElementById(o);if(!i)return;const c=i.getAttribute("data-expanded")==="true",p=i.getAttribute("data-full")||"",g=i.getAttribute("data-truncated")||"";c?(i.textContent=g+"...",i.setAttribute("data-expanded","false"),n.textContent="Read more"):(i.textContent=p,i.setAttribute("data-expanded","true"),n.textContent="Read less")})})}expandColumn(e,t){if(!e||!t)return;const a=this.incidents.find(o=>o.id===parseInt(e));if(!a)return;const s=document.querySelector(`[data-expand-target="${e}-${t}"]`),n=document.querySelector(`[data-incident-id="${e}"][data-target="${t}"]`);if(!s||!n)return;if(s.getAttribute("data-expanded")==="true"){let o="";if(t==="persons"&&a.persons_involved&&a.persons_involved.length>0){const i=a.persons_involved[0];o=`
                    <span class="inline-block bg-purple-200 text-purple-900 px-2 py-0.5 rounded text-xs font-semibold mb-1">${i.person_type.toUpperCase()}</span>
                    <div class="ml-1 text-xs">
                        <div><span class="font-medium text-gray-700">Name:</span> <span class="blur-text-badge">${i.first_name}</span></div>
                        <div><span class="font-medium text-gray-700">Contact:</span> <span class="blur-text-badge">${i.contact_number}</span></div>
                        <div><span class="font-medium text-gray-700">Other:</span> <span class="blur-text-badge">${i.other_info}</span></div>
                    </div>
                `}else if(t==="evidence"&&a.evidence&&a.evidence.length>0){const i=a.evidence[0];o=`
                    <span class="inline-block bg-orange-200 text-orange-900 px-2 py-0.5 rounded text-xs font-semibold mb-1">${i.evidence_type}</span>
                    <div class="ml-1 text-xs">
                        <div><span class="font-medium text-gray-700">Desc:</span> <span class="blur-text-badge">${i.description}</span></div>
                        <div><span class="font-medium text-gray-700">Link:</span> <span class="blur-text-badge">${i.evidence_link}</span></div>
                    </div>
                `}s.innerHTML=o,s.setAttribute("data-expanded","false"),n.textContent=`See more (${t==="persons"?a.persons_involved.length-1:a.evidence.length-1} more)`,console.log(`Collapsed ${t} for incident ${e}`)}else{let o="";t==="persons"?a.persons_involved&&a.persons_involved.length>0&&(o=a.persons_involved.map(i=>`
                        <div class="text-xs mb-2 pb-2 border-b border-gray-200 last:border-b-0">
                            <span class="inline-block bg-purple-200 text-purple-900 px-2 py-0.5 rounded text-xs font-semibold mb-1">${i.person_type.toUpperCase()}</span>
                            <div class="ml-1">
                                <div><span class="font-medium text-gray-700">Name:</span> <span class="blur-text-badge">${i.first_name}</span></div>
                                <div><span class="font-medium text-gray-700">Contact:</span> <span class="blur-text-badge">${i.contact_number}</span></div>
                                <div><span class="font-medium text-gray-700">Other:</span> <span class="blur-text-badge">${i.other_info}</span></div>
                            </div>
                        </div>
                    `).join("")):t==="evidence"&&a.evidence&&a.evidence.length>0&&(o=a.evidence.map(i=>`
                        <div class="text-xs mb-2 pb-2 border-b border-gray-200 last:border-b-0">
                            <span class="inline-block bg-orange-200 text-orange-900 px-2 py-0.5 rounded text-xs font-semibold mb-1">${i.evidence_type}</span>
                            <div class="ml-1">
                                <div><span class="font-medium text-gray-700">Desc:</span> <span class="blur-text-badge">${i.description}</span></div>
                                <div><span class="font-medium text-gray-700">Link:</span> <span class="blur-text-badge">${i.evidence_link}</span></div>
                            </div>
                        </div>
                    `).join("")),s.innerHTML=o,s.setAttribute("data-expanded","true"),n.textContent="See less",console.log(`Expanded ${t} for incident ${e}`)}}setupSidebarToggle(){const e=document.getElementById("sidebarToggle"),t=document.querySelector("aside"),a=document.getElementById("sidebarOverlay");e==null||e.addEventListener("click",()=>{t==null||t.classList.toggle("-translate-x-full"),a==null||a.classList.toggle("hidden")}),a==null||a.addEventListener("click",()=>{t==null||t.classList.add("-translate-x-full"),a==null||a.classList.add("hidden")});const s=t==null?void 0:t.querySelectorAll("a, button");s==null||s.forEach(n=>{n.addEventListener("click",()=>{window.innerWidth<1024&&(t==null||t.classList.add("-translate-x-full"),a==null||a.classList.add("hidden"))})})}showSkeletonLoaders(){try{for(let e=1;e<=5;e++){const t=document.getElementById(`skeletonRow${e}`);t&&t instanceof HTMLElement&&(t.style.display="")}}catch(e){console.error("Error showing skeleton loaders:",e)}}hideSkeletonLoaders(){try{for(let e=1;e<=5;e++){const t=document.getElementById(`skeletonRow${e}`);t&&t instanceof HTMLElement&&(t.style.display="none")}}catch(e){console.error("Error hiding skeleton loaders:",e)}}async loadInitialData(){try{this.showSkeletonLoaders();const t=await(await fetch("/api/crimes")).json();console.log("📊 API Response received:",t),this.incidents=t.incidents||[],console.log("📋 Incidents loaded:",this.incidents.length),console.log("👥 First incident:",this.incidents[0]),this.updateCategories(t.categories||[]),this.updateBarangays(t.barangays||[]),this.applyFilters(),this.updateStats()}catch(e){console.error("Error loading crime data:",e),this.hideSkeletonLoaders(),this.showError("Failed to load crime data")}}applyFilters(){this.showSkeletonLoaders(),setTimeout(()=>{this.filteredIncidents=this.incidents.filter(e=>{var d,o;const t=this.filters.search.toLowerCase(),a=e.incident_title.toLowerCase().includes(t),s=e.incident_code.toLowerCase().includes(t),n=e.incident_title.toLowerCase().includes(this.filters.search.toLowerCase());return e.incident_code.toLowerCase().includes(this.filters.search.toLowerCase()),!(t&&!(a||s||n)||this.filters.category&&((d=e.category)==null?void 0:d.category_name)!==this.filters.category||this.filters.status&&e.status!==this.filters.status||this.filters.barangay&&((o=e.barangay)==null?void 0:o.barangay_name)!==this.filters.barangay||this.filters.date&&new Date(e.incident_date).toISOString().split("T")[0]!==this.filters.date||this.filters.status&&e.status!==this.filters.status&&!["reported","under_investigation","solved","closed","archived"].includes(e.status)||this.filters.clearance&&e.clearance_status!==this.filters.clearance&&!["cleared","uncleared"].includes(e.clearance_status))}),this.currentPage=1,this.renderTable()},300)}updateStats(){const e={total:this.incidents.length,live:this.incidents.filter(t=>t.status==="live").length,underInvestigation:this.incidents.filter(t=>t.status==="under investigation").length,cleared:this.incidents.filter(t=>t.status==="cleared").length};this.updateStatCard("totalCount",e.total),this.updateStatCard("liveCount",e.live),this.updateStatCard("investigationCount",e.underInvestigation),this.updateStatCard("clearedCount",e.cleared)}updateStatCard(e,t){const a=document.getElementById(e);a&&(a.textContent=t.toString())}updateCategories(e){const t=window.crimePage.categoryFilter,a=document.getElementById("modalCrimeCategory");if(t){for(;t.children.length>1;)t.removeChild(t.lastChild);e.forEach(s=>{const n=document.createElement("option");n.value=s.category_name,n.textContent=s.category_name,t.appendChild(n)})}if(a){for(;a.children.length>1;)a.removeChild(a.lastChild);e.forEach(s=>{const n=document.createElement("option");n.value=s.id,n.textContent=s.category_name,a.appendChild(n)})}}updateBarangays(e){const t=window.crimePage.barangayFilter,a=document.getElementById("modalBarangay");if(t){for(;t.children.length>1;)t.removeChild(t.lastChild);e.forEach(s=>{const n=document.createElement("option");n.value=s.barangay_name,n.textContent=s.barangay_name,t.appendChild(n)})}if(a){for(;a.children.length>1;)a.removeChild(a.lastChild);e.forEach(s=>{const n=document.createElement("option");n.value=s.id,n.textContent=s.barangay_name,a.appendChild(n)})}}renderTable(){const e=document.getElementById("crimesTableBody");if(!e||!(e instanceof HTMLElement))return;const t=(this.currentPage-1)*this.pageSize,a=t+this.pageSize,s=this.filteredIncidents.slice(t,a);if(this.hideSkeletonLoaders(),s.length===0){e.innerHTML=`
                <tr>
                    <td colspan="8" class="px-4 py-8 text-center text-gray-500">
                        <i class="fas fa-search text-4xl mb-4 block text-gray-300"></i>
                        <p class="text-lg font-medium">No incidents found</p>
                        <p class="text-sm">Try adjusting your filters</p>
                    </td>
                </tr>
            `;return}e.innerHTML="",s.forEach(n=>{var h,r;const d=this.getStatusBadge(n.status),o=this.getClearanceBadge(n.clearance_status),i=`
                <div class="text-xs space-y-1">
                    <div><span class="font-medium text-gray-700">Barangay:</span> ${((h=n.barangay)==null?void 0:h.barangay_name)||"N/A"}</div>
                    <div><span class="font-medium text-gray-700">Address:</span> ${n.address_details||"N/A"}</div>
                    <div class="flex gap-2">
                        <span><span class="font-medium text-gray-700">Lat:</span> <span class="font-mono">${n.latitude||"N/A"}</span></span>
                        <span><span class="font-medium text-gray-700">Lng:</span> <span class="font-mono">${n.longitude||"N/A"}</span></span>
                    </div>
                </div>
            `,c=n.incident_description||"N/A",p=n.modus_operandi||"N/A",g=c.length>50,m=p.length>40,u=`
                <div class="text-xs space-y-1">
                    <div>
                        <span class="font-medium text-gray-700">Description:</span>
                        <div class="text-gray-600 mt-0.5">
                            <span id="desc-text-${n.id}" data-full="${c}" data-truncated="${c.substring(0,50)}">${c.substring(0,50)}${g?"...":""}</span>
                            ${g?`<button class="read-more-btn text-blue-600 hover:text-blue-800 text-xs ml-1 font-semibold" data-incident-id="${n.id}" data-target="desc-text-${n.id}">Read more</button>`:""}
                        </div>
                    </div>
                    <div>
                        <span class="font-medium text-gray-700">M.O.:</span>
                        <div class="text-gray-600 mt-0.5">
                            <span id="mo-text-${n.id}" data-full="${p}" data-truncated="${p.substring(0,40)}">${p.substring(0,40)}${m?"...":""}</span>
                            ${m?`<button class="read-more-btn text-blue-600 hover:text-blue-800 text-xs ml-1 font-semibold" data-incident-id="${n.id}" data-target="mo-text-${n.id}">Read more</button>`:""}
                        </div>
                    </div>
                    <div><span class="font-medium text-gray-700">Weather:</span> ${n.weather_condition||"N/A"}</div>
                    <div><span class="font-medium text-gray-700">Officer:</span> ${n.assigned_officer||"N/A"}</div>
                </div>
            `,l=`
                <div class="text-xs space-y-1">
                    <div><span class="font-medium text-gray-700">Victims:</span> ${n.victim_count||0}</div>
                    <div><span class="font-medium text-gray-700">Suspects:</span> ${n.suspect_count||0}</div>
                    <div class="mt-1 pt-1 border-t border-gray-200">${d}</div>
                    <div class="mt-1">${o}</div>
                </div>
            `;let b='<span class="text-gray-500 text-xs">None</span>';if(n.persons_involved&&n.persons_involved.length>0){const x=n.persons_involved[0],f=n.persons_involved.length;b=`
                    <div data-expand-target="${n.id}-persons" class="text-xs mb-2 pb-2">
                        <span class="inline-block bg-purple-200 text-purple-900 px-2 py-0.5 rounded text-xs font-semibold mb-1">${x.person_type.toUpperCase()}</span>
                        <div class="ml-1">
                            <div><span class="font-medium text-gray-700">Name:</span> <span class="blur-text-badge">${x.first_name}</span></div>
                            <div><span class="font-medium text-gray-700">Contact:</span> <span class="blur-text-badge">${x.contact_number}</span></div>
                            <div><span class="font-medium text-gray-700">Other:</span> <span class="blur-text-badge">${x.other_info}</span></div>
                        </div>
                    </div>
                    ${f>1?`<button class="see-more-button text-xs text-blue-600 hover:text-blue-800 font-semibold" data-incident-id="${n.id}" data-target="persons">See more (${f-1} more)</button>`:""}
                `}let y='<span class="text-gray-500 text-xs">None</span>';if(n.evidence&&n.evidence.length>0){const x=n.evidence[0],f=n.evidence.length;y=`
                    <div data-expand-target="${n.id}-evidence" class="text-xs mb-2 pb-2">
                        <span class="inline-block bg-orange-200 text-orange-900 px-2 py-0.5 rounded text-xs font-semibold mb-1">${x.evidence_type}</span>
                        <div class="ml-1">
                            <div><span class="font-medium text-gray-700">Desc:</span> <span class="blur-text-badge">${x.description}</span></div>
                            <div><span class="font-medium text-gray-700">Link:</span> <span class="blur-text-badge">${x.evidence_link}</span></div>
                        </div>
                    </div>
                    ${f>1?`<button class="see-more-button text-xs text-blue-600 hover:text-blue-800 font-semibold" data-incident-id="${n.id}" data-target="evidence">See more (${f-1} more)</button>`:""}
                `}const v=document.createElement("tr");v.className="hover:bg-gray-50 transition-colors border border-gray-200",v.innerHTML=`
                <td class="px-3 py-3 text-sm border-r border-gray-200">
                    <input type="checkbox" class="w-4 h-4 rounded border-gray-300 cursor-pointer" data-incident-id="${n.id}">
                </td>
                <td class="px-4 py-3 text-sm font-medium text-gray-900 border-r border-gray-200">
                    ${n.incident_code}
                </td>
                <td class="px-4 py-3 text-sm text-gray-900 border-r border-gray-200 max-w-xs">
                    <div class="font-medium">${n.incident_title}</div>
                    <div class="text-xs text-gray-500">${((r=n.category)==null?void 0:r.category_name)||"Unknown"}</div>
                </td>
                <td class="px-4 py-3 text-sm text-gray-900 border-r border-gray-200 min-w-40">
                    ${i}
                </td>
                <td class="px-4 py-3 text-sm text-gray-900 border-r border-gray-200">
                    ${this.formatDate(n.incident_date)}
                </td>
                <td class="px-4 py-3 text-sm text-gray-900 border-r border-gray-200 min-w-48">
                    ${u}
                </td>
                <td class="px-4 py-3 text-sm text-gray-900 border-r border-gray-200 min-w-40">
                    ${l}
                </td>
                <td class="px-4 py-3 text-xs border-r border-gray-200 min-w-40 max-h-32 overflow-y-auto">
                    ${b}
                </td>
                <td class="px-4 py-3 text-xs border-r border-gray-200 min-w-40 max-h-32 overflow-y-auto">
                    ${y}
                </td>
                <td class="px-4 py-3 text-sm border-gray-200">
                    <div class="flex gap-1">
                        <button onclick="event.stopPropagation(); crimePageManager.viewIncident(${n.id})"
                            class="px-2 py-1 text-xs bg-blue-500 text-white rounded hover:bg-blue-600 transition-colors" title="View">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button onclick="event.stopPropagation(); crimePageManager.editIncident(${n.id})"
                            class="px-2 py-1 text-xs bg-yellow-500 text-white rounded hover:bg-yellow-600 transition-colors" title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button onclick="event.stopPropagation(); crimePageManager.deleteIncident(${n.id})"
                            class="px-2 py-1 text-xs bg-red-500 text-white rounded hover:bg-red-600 transition-colors" title="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            `,e.appendChild(v)}),this.setupTableCheckboxListeners(),this.updatePagination()}getStatusBadge(e){return`<span class="inline-block px-2 py-1 text-xs font-semibold rounded ${{reported:"bg-red-100 text-red-800",under_investigation:"bg-yellow-100 text-yellow-800",solved:"bg-green-100 text-green-800",closed:"bg-blue-100 text-blue-800",archived:"bg-gray-100 text-gray-800"}[e]||"bg-blue-100 text-blue-800"}">${this.capitalizeFirst(e)}</span>`}getClearanceBadge(e){return`<span class="inline-block px-2 py-1 text-xs font-semibold rounded ${e==="cleared"?"bg-green-100 text-green-800":"bg-red-100 text-red-800"}">${this.capitalizeFirst(e)}</span>`}capitalizeFirst(e){return e.charAt(0).toUpperCase()+e.slice(1)}formatDate(e){try{return new Date(e).toLocaleDateString("en-US",{year:"numeric",month:"short",day:"numeric"})}catch{return"N/A"}}renderRecentIncidents(){const e=document.getElementById("recentIncidents");if(!e)return;const t=this.filteredIncidents.slice(0,5);if(t.length===0){e.innerHTML=`
                <div class="p-4 text-center text-gray-500">
                    <i class="fas fa-inbox text-2xl mb-2 block text-gray-300"></i>
                    <p class="text-sm">No recent incidents</p>
                </div>
            `;return}e.innerHTML=t.map(a=>{var s;return`
            <div class="p-3 border-b border-gray-100 hover:bg-gray-50 cursor-pointer transition-colors" 
                 onclick="crimePageManager.viewIncident(${a.id})">
                <div class="flex items-start gap-3">
                    <div class="w-8 h-8 bg-${this.getCategoryColor(a)}-100 rounded-lg flex items-center justify-center flex-shrink-0">
                        <i class="fas ${this.getCategoryIcon(a)} text-${this.getCategoryColor(a)}-600 text-sm"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <h4 class="text-sm font-medium text-gray-900 truncate">${a.incident_title}</h4>
                        <p class="text-xs text-gray-600">${((s=a.barangay)==null?void 0:s.barangay_name)||"Unknown"}</p>
                        <div class="flex gap-2 mt-1">
                            ${this.getStatusBadge(a.status)}
                        </div>
                    </div>
                </div>
            </div>
        `}).join("")}getCategoryColor(e){var t,a;return((a=(t=e.category)==null?void 0:t.color_code)==null?void 0:a.replace("#",""))||"alertara"}getCategoryIcon(e){var t;return((t=e.category)==null?void 0:t.icon)||"fa-exclamation-circle"}updatePagination(){const e=document.getElementById("pagination"),t=document.getElementById("showingStart"),a=document.getElementById("showingEnd"),s=document.getElementById("totalRecords");if(!e||!t||!a||!s)return;const n=this.filteredIncidents.length,d=(this.currentPage-1)*this.pageSize+1,o=Math.min(this.currentPage*this.pageSize,n),i=Math.ceil(n/this.pageSize);t.textContent=d.toString(),a.textContent=o.toString(),s.textContent=n.toString();let c="";this.currentPage>1&&(c+=`
                <button onclick="crimePageManager.goToPage(${this.currentPage-1})" 
                        class="px-3 py-1 text-sm border border-gray-300 rounded-l hover:bg-gray-50 transition-colors">
                    <i class="fas fa-chevron-left"></i>
                </button>
            `);const p=Math.max(1,this.currentPage-2),g=Math.min(i,this.currentPage+2);for(let m=p;m<=g;m++){const l=m===this.currentPage?"px-3 py-1 text-sm bg-alertara-600 text-white border-alertara-600":"px-3 py-1 text-sm border border-gray-300 hover:bg-gray-50 transition-colors";c+=`
                <button onclick="crimePageManager.goToPage(${m})" class="${l}">
                    ${m}
                </button>
            `}this.currentPage<i&&(c+=`
                <button onclick="crimePageManager.goToPage(${this.currentPage+1})" 
                        class="px-3 py-1 text-sm border border-gray-300 rounded-r hover:bg-gray-50 transition-colors">
                    <i class="fas fa-chevron-right"></i>
                </button>
            `),e.innerHTML=c}viewIncident(e){var t,a;try{const s=this.incidents.find(i=>i.id===e);if(!s)return;const n=document.createElement("div");n.id="viewIncidentModal",n.className="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4 overflow-y-auto";let d='<p class="text-gray-500 text-sm italic">No persons involved</p>';s.persons_involved&&s.persons_involved.length>0&&(d=s.persons_involved.map(i=>`
                    <div class="p-4 bg-purple-50 rounded-lg border border-purple-200 mb-3">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="inline-block bg-purple-200 text-purple-900 px-2 py-1 rounded text-xs font-semibold">${i.person_type.toUpperCase()}</span>
                        </div>
                        <div class="grid grid-cols-2 gap-3 text-sm">
                            <div>
                                <label class="font-medium text-gray-700">First Name:</label>
                                <div class="blur-text-badge mt-1 text-gray-600">${i.first_name}</div>
                            </div>
                            <div>
                                <label class="font-medium text-gray-700">Middle Name:</label>
                                <div class="blur-text-badge mt-1 text-gray-600">${i.middle_name}</div>
                            </div>
                            <div>
                                <label class="font-medium text-gray-700">Last Name:</label>
                                <div class="blur-text-badge mt-1 text-gray-600">${i.last_name}</div>
                            </div>
                            <div>
                                <label class="font-medium text-gray-700">Contact Number:</label>
                                <div class="blur-text-badge mt-1 text-gray-600">${i.contact_number}</div>
                            </div>
                            <div class="col-span-2">
                                <label class="font-medium text-gray-700">Additional Info:</label>
                                <div class="blur-text-badge mt-1 text-gray-600">${i.other_info}</div>
                            </div>
                        </div>
                    </div>
                `).join(""));let o='<p class="text-gray-500 text-sm italic">No evidence recorded</p>';s.evidence&&s.evidence.length>0&&(o=s.evidence.map(i=>`
                    <div class="p-4 bg-orange-50 rounded-lg border border-orange-200 mb-3">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="inline-block bg-orange-200 text-orange-900 px-2 py-1 rounded text-xs font-semibold">${i.evidence_type}</span>
                        </div>
                        <div class="space-y-3 text-sm">
                            <div>
                                <label class="font-medium text-gray-700">Description:</label>
                                <div class="blur-text-badge mt-1 text-gray-600">${i.description}</div>
                            </div>
                            <div>
                                <label class="font-medium text-gray-700">Evidence Link:</label>
                                <div class="blur-text-badge mt-1 text-gray-600">${i.evidence_link}</div>
                            </div>
                        </div>
                    </div>
                `).join("")),n.innerHTML=`
                <div class="bg-white rounded-xl shadow-2xl max-w-4xl w-full max-h-[90vh] flex flex-col">
                    <!-- Header -->
                    <div class="flex items-center justify-between p-6 border-b border-gray-200 bg-gradient-to-r from-alertara-50 to-alertara-100 sticky top-0">
                        <div>
                            <h2 class="text-2xl font-bold text-gray-900">${s.incident_code}</h2>
                            <p class="text-sm text-gray-600 mt-1">${s.incident_title}</p>
                        </div>
                        <button onclick="document.getElementById('viewIncidentModal').remove()" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times text-2xl"></i>
                        </button>
                    </div>

                    <!-- Content -->
                    <div class="flex-1 overflow-y-auto p-6 space-y-6">
                        <!-- Basic Info -->
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                                <i class="fas fa-info-circle text-blue-600"></i>
                                Basic Information
                            </h3>
                            <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                                <div class="bg-gray-50 p-3 rounded-lg">
                                    <label class="text-xs font-medium text-gray-600 uppercase">Code</label>
                                    <p class="text-gray-900 mt-1">${s.incident_code}</p>
                                </div>
                                <div class="bg-gray-50 p-3 rounded-lg">
                                    <label class="text-xs font-medium text-gray-600 uppercase">Category</label>
                                    <p class="text-gray-900 mt-1">${((t=s.category)==null?void 0:t.category_name)||"N/A"}</p>
                                </div>
                                <div class="bg-gray-50 p-3 rounded-lg">
                                    <label class="text-xs font-medium text-gray-600 uppercase">Date</label>
                                    <p class="text-gray-900 mt-1">${this.formatDate(s.incident_date)}</p>
                                </div>
                                <div class="bg-gray-50 p-3 rounded-lg">
                                    <label class="text-xs font-medium text-gray-600 uppercase">Status</label>
                                    <div class="mt-1">${this.getStatusBadge(s.status)}</div>
                                </div>
                                <div class="bg-gray-50 p-3 rounded-lg">
                                    <label class="text-xs font-medium text-gray-600 uppercase">Clearance</label>
                                    <div class="mt-1">${this.getClearanceBadge(s.clearance_status)}</div>
                                </div>
                                <div class="bg-gray-50 p-3 rounded-lg">
                                    <label class="text-xs font-medium text-gray-600 uppercase">Time</label>
                                    <p class="text-gray-900 mt-1">${s.incident_time||"N/A"}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Description -->
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                                <i class="fas fa-file-alt text-blue-600"></i>
                                Description & Details
                            </h3>
                            <div class="bg-gray-50 p-4 rounded-lg space-y-4">
                                <div>
                                    <label class="font-medium text-gray-700">Description:</label>
                                    <p class="text-gray-600 mt-2">${s.incident_description||"N/A"}</p>
                                </div>
                                <div>
                                    <label class="font-medium text-gray-700">Modus Operandi:</label>
                                    <p class="text-gray-600 mt-2">${s.modus_operandi||"N/A"}</p>
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="font-medium text-gray-700">Weather Condition:</label>
                                        <p class="text-gray-600 mt-2">${s.weather_condition||"N/A"}</p>
                                    </div>
                                    <div>
                                        <label class="font-medium text-gray-700">Assigned Officer:</label>
                                        <p class="text-gray-600 mt-2">${s.assigned_officer||"N/A"}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Location -->
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                                <i class="fas fa-map-marker-alt text-red-600"></i>
                                Location
                            </h3>
                            <div class="bg-gray-50 p-4 rounded-lg space-y-3">
                                <div>
                                    <label class="font-medium text-gray-700">Barangay:</label>
                                    <p class="text-gray-600 mt-1">${((a=s.barangay)==null?void 0:a.barangay_name)||"N/A"}</p>
                                </div>
                                <div>
                                    <label class="font-medium text-gray-700">Address:</label>
                                    <p class="text-gray-600 mt-1">${s.address_details||"N/A"}</p>
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="font-medium text-gray-700">Latitude:</label>
                                        <p class="text-gray-600 font-mono mt-1">${s.latitude||"N/A"}</p>
                                    </div>
                                    <div>
                                        <label class="font-medium text-gray-700">Longitude:</label>
                                        <p class="text-gray-600 font-mono mt-1">${s.longitude||"N/A"}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Statistics -->
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                                <i class="fas fa-chart-bar text-green-600"></i>
                                Statistics
                            </h3>
                            <div class="grid grid-cols-2 gap-4">
                                <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
                                    <label class="text-sm font-medium text-blue-700">Victim Count</label>
                                    <p class="text-3xl font-bold text-blue-900 mt-1">${s.victim_count||0}</p>
                                </div>
                                <div class="bg-red-50 p-4 rounded-lg border border-red-200">
                                    <label class="text-sm font-medium text-red-700">Suspect Count</label>
                                    <p class="text-3xl font-bold text-red-900 mt-1">${s.suspect_count||0}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Persons Involved -->
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                                <i class="fas fa-users text-purple-600"></i>
                                Complainant/Persons Involved (${s.persons_involved_count||0})
                            </h3>
                            ${d}
                        </div>

                        <!-- Evidence -->
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                                <i class="fas fa-fingerprint text-orange-600"></i>
                                Evidence (${s.evidence_count||0})
                            </h3>
                            ${o}
                        </div>
                    </div>
                </div>
            `,document.body.appendChild(n),n.addEventListener("click",i=>{i.target===n&&n.remove()})}catch(s){console.error("Error viewing incident:",s),this.showError("Error loading incident details")}}editIncident(e){window.location.href=`/crime-incident/${e}/edit`}deleteIncident(e){confirm("Are you sure you want to delete this incident?")&&console.log("Delete incident:",e)}async showDetailsModal(e){try{const a=await(await fetch(`/api/crime-incident/${e}/details`)).json();if(!a.success){this.showError("Failed to load incident details");return}this.displayDetailsModal(a)}catch(t){console.error("Error fetching incident details:",t),this.showError("Error loading incident details")}}displayDetailsModal(e){const t=document.createElement("div");t.id="detailsModal",t.className="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4";const a=e.incident,s=e.persons_involved||[],n=e.evidence||[];let d="";s.length===0?d='<p class="text-gray-500 text-sm italic">No persons involved recorded</p>':d=s.map(i=>`
                <div class="p-3 bg-gray-50 rounded-lg border border-gray-200 mb-2">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <span class="text-xs font-medium bg-blue-100 text-blue-800 px-2 py-1 rounded">${i.person_type}</span>
                            <div class="mt-2 text-sm text-gray-700">
                                <div class="blur-text font-medium" title="Encrypted - Decryption coming soon">██████████ ██████</div>
                                <div class="blur-text text-xs text-gray-600 mt-1" title="Encrypted - Decryption coming soon">██████████████</div>
                            </div>
                        </div>
                    </div>
                </div>
            `).join("");let o="";n.length===0?o='<p class="text-gray-500 text-sm italic">No evidence recorded</p>':o=n.map(i=>`
                <div class="p-3 bg-gray-50 rounded-lg border border-gray-200 mb-2">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <span class="text-xs font-medium bg-green-100 text-green-800 px-2 py-1 rounded">${i.evidence_type}</span>
                            <div class="mt-2 text-sm text-gray-700">
                                <div class="blur-text" title="Encrypted - Decryption coming soon">████████████████████</div>
                            </div>
                        </div>
                    </div>
                </div>
            `).join(""),t.innerHTML=`
            <div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full max-h-[90vh] flex flex-col">
                <!-- Header -->
                <div class="flex items-center justify-between p-6 border-b border-gray-200 bg-gradient-to-r from-alertara-50 to-alertara-100">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900">${a.incident_code}</h2>
                        <p class="text-sm text-gray-600 mt-1">${a.incident_title}</p>
                    </div>
                    <button onclick="document.getElementById('detailsModal').remove()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-2xl"></i>
                    </button>
                </div>

                <!-- Content -->
                <div class="flex-1 overflow-y-auto p-6 space-y-6">
                    <!-- Incident Info -->
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-3">Incident Information</h3>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-xs text-gray-600 font-medium">Category</p>
                                <p class="text-sm text-gray-900">${a.category.category_name}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-600 font-medium">Location</p>
                                <p class="text-sm text-gray-900">${a.barangay.barangay_name}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-600 font-medium">Date</p>
                                <p class="text-sm text-gray-900">${this.formatDate(a.incident_date)}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-600 font-medium">Status</p>
                                <p class="text-sm">${this.getStatusBadge(a.status)}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Persons Involved -->
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-3 flex items-center gap-2">
                            <i class="fas fa-users text-purple-600"></i>
                            Persons Involved (${s.length})
                        </h3>
                        <div class="space-y-2">
                            ${d}
                        </div>
                        <p class="text-xs text-gray-500 mt-3 italic">⚠️ Sensitive information is encrypted and blurred for security. Decryption will be available in a future update.</p>
                    </div>

                    <!-- Evidence -->
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-3 flex items-center gap-2">
                            <i class="fas fa-fingerprint text-orange-600"></i>
                            Evidence (${n.length})
                        </h3>
                        <div class="space-y-2">
                            ${o}
                        </div>
                        <p class="text-xs text-gray-500 mt-3 italic">⚠️ Sensitive information is encrypted and blurred for security. Decryption will be available in a future update.</p>
                    </div>
                </div>

                <!-- Footer -->
                <div class="flex items-center justify-end gap-3 p-6 border-t border-gray-200 bg-gray-50">
                    <button onclick="document.getElementById('detailsModal').remove()" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                        Close
                    </button>
                </div>
            </div>
        `,document.body.appendChild(t),t.addEventListener("click",i=>{i.target===t&&t.remove()})}exportData(){const e=this.generateCSV(),t=new Blob([e],{type:"text/csv"}),a=window.URL.createObjectURL(t),s=document.createElement("a");s.href=a,s.download=`crime-incidents-${new Date().toISOString().split("T")[0]}.csv`,document.body.appendChild(s),s.click(),document.body.removeChild(s),window.URL.revokeObjectURL(a)}generateCSV(){const e=["Code","Title","Category","Barangay","Date","Status","Clearance"],t=this.filteredIncidents.map(a=>{var s,n;return[a.incident_code,a.incident_title,((s=a.category)==null?void 0:s.category_name)||"Unknown",((n=a.barangay)==null?void 0:n.barangay_name)||"Unknown",this.formatDate(a.incident_date),a.status,a.clearance_status]});return[e,...t].map(a=>a.map(s=>`"${s}"`).join(",")).join(`
`)}openModal(){const e=document.getElementById("incidentModal");e&&e instanceof HTMLElement&&(e.classList.remove("hidden"),document.body&&document.body.style&&(document.body.style.overflow="hidden"))}closeModal(){const e=document.getElementById("incidentModal");e&&e instanceof HTMLElement&&(e.classList.add("hidden"),document.body&&document.body.style&&(document.body.style.overflow="auto"))}showAddIncidentModal(){const e=document.getElementById("addIncidentModal");e&&e instanceof HTMLElement&&(e.classList.remove("hidden"),document.body&&document.body.style&&(document.body.style.overflow="hidden"),this.loadCategoriesIntoModal())}closeAddIncidentModal(){const e=document.getElementById("addIncidentModal");e&&e instanceof HTMLElement&&(e.classList.add("hidden"),document.body&&document.body.style&&(document.body.style.overflow="auto"),this.resetAddIncidentForm())}loadCategoriesIntoModal(){const e=document.getElementById("modalCrimeCategory");if(e&&this.incidents.length>0){const a=[...new Map(this.incidents.filter(s=>s.category&&s.category.id).map(s=>[s.category.id,s.category])).values()].map(s=>`<option value="${s.id}">${s.category_name}</option>`);e.innerHTML='<option value="">Select a category...</option>'+a.join("")}}resetAddIncidentForm(){const e=document.getElementById("addIncidentForm");e&&e.reset()}async submitIncidentForm(){var n;const e=document.getElementById("addIncidentForm"),t=e==null?void 0:e.querySelector('button[type="submit"]');if(!e||!t)return;const a=t.innerHTML;t.disabled=!0,t.innerHTML='<i class="fas fa-spinner fa-spin mr-2"></i>Saving...';const s=new FormData(e);try{const d=window.personsInvolvedList||[],o=window.evidenceList||[],i=d.map(l=>({person_type:l.type,first_name:l.firstName,middle_name:l.middleName||"",last_name:l.lastName,contact_number:l.contactNumber||"",other_info:l.otherInfo||""})),c=o.map(l=>({evidence_type:l.type,description:l.description||"",evidence_link:l.link||""})),p={incident_title:s.get("incident_title"),incident_description:s.get("incident_description"),crime_category_id:s.get("crime_category_id"),barangay_id:s.get("barangay_id"),incident_date:s.get("incident_date"),incident_time:s.get("incident_time"),latitude:s.get("latitude"),longitude:s.get("longitude"),address_details:s.get("address_details"),victim_count:s.get("victim_count")||0,suspect_count:s.get("suspect_count")||0,modus_operandi:s.get("modus_operandi"),weather_condition:s.get("weather_condition"),assigned_officer:s.get("assigned_officer"),status:s.get("status"),clearance_status:s.get("clearance_status"),clearance_date:s.get("clearance_date"),persons_involved:i,evidence_items:c},g=await fetch("/crime-incident",{method:"POST",headers:{"X-CSRF-TOKEN":((n=document.querySelector('meta[name="csrf-token"]'))==null?void 0:n.getAttribute("content"))||"","Content-Type":"application/json",Accept:"application/json"},body:JSON.stringify(p)}),m=g.headers.get("content-type");if(!m||!m.includes("application/json")){const l=await g.text();console.error("❌ Non-JSON response:",l),this.showError("Server error: Expected JSON response but got HTML. Check browser console.");return}const u=await g.json();g.ok?(console.log("✅ Incident saved successfully:",u),this.showSuccess("✅ Incident saved successfully!"),this.closeAddIncidentModal(),console.log("⏳ Waiting for real-time broadcast to update the table...")):(console.error("❌ Error response:",u),this.showError("Failed to create incident: "+(u.message||"Unknown error")))}catch(d){console.error("❌ Error creating incident:",d),this.showError("An error occurred while creating the incident")}finally{t.disabled=!1,t.innerHTML=a}}showSuccess(e){const t=document.createElement("div");t.className="fixed top-4 right-4 bg-green-500 text-white px-4 py-2 rounded-lg shadow-lg z-50",t.textContent=e,document.body.appendChild(t),setTimeout(()=>{document.body.removeChild(t)},3e3)}initializeRealtimeListeners(){if(console.log("🔍 Initializing real-time listeners for crime incidents..."),"Notification"in window&&Notification.permission==="default"&&Notification.requestPermission().then(e=>{console.log("🔔 Notification permission:",e),console.log(e==="granted"?"✅ Browser notifications enabled":"⚠️ Browser notifications denied, will use custom notifications")}).catch(e=>{console.error("❌ Error requesting notification permission:",e)}),typeof window.Echo<"u"&&window.Echo){console.log("🔌 Echo available - Setting up real-time listeners..."),window.Echo.connector.pusher.connection.bind("connected",function(){console.log("✅ Pusher connected successfully")}),window.Echo.connector.pusher.connection.bind("disconnected",function(){console.log("❌ Pusher disconnected")}),window.Echo.connector.pusher.connection.bind("error",function(t){console.error("❌ Pusher connection error:",t)});const e=window.Echo.channel("crime-incidents");e.subscribed(function(){console.log("✅ Subscribed to crime-incidents channel")}),e.listen(".incident.created",t=>{console.log("🆕 New incident created:",t),this.handleNewIncident(t)}),e.listen(".incident.updated",t=>{console.log("📝 Incident updated:",t),this.handleUpdatedIncident(t)}),e.listen(".incident.deleted",t=>{console.log("🗑️ Incident deleted:",t),this.handleDeletedIncident(t)}),console.log("✅ Real-time listeners setup complete")}else console.warn("⚠️ Echo not available - real-time features disabled")}handleNewIncident(e){var t;if(console.log("📢 Handling new incident from WebSocket broadcast"),(t=window.NotificationManager)==null||t.showIncidentNotification("New Incident Created!",e,"created"),e&&e.id){const a={id:e.id,incident_code:e.incident_code||"INC-"+Date.now(),incident_title:e.incident_title||"New Incident",incident_date:e.incident_date||new Date().toISOString().split("T")[0],status:e.status||"reported",clearance_status:e.clearance_status||"uncleared",category:{id:e.crime_category_id||0,category_name:e.category_name||"Unknown"},barangay:{id:e.barangay_id||0,barangay_name:e.location||"Unknown"}};this.incidents.unshift(a),this.applyFilters(),this.updateStats(),console.log("✅ Incident added to local data, table will refresh automatically")}}handleUpdatedIncident(e){var t;if(console.log("📢 Handling updated incident from WebSocket broadcast"),(t=window.NotificationManager)==null||t.showIncidentNotification("Incident Updated",e,"updated"),e&&e.id){const a=this.incidents.findIndex(s=>s.id===e.id);a!==-1&&(this.incidents[a]={...this.incidents[a],incident_title:e.incident_title,status:e.status,clearance_status:e.clearance_status}),this.applyFilters(),this.updateStats(),console.log("✅ Incident updated in local data")}}handleDeletedIncident(e){var t;console.log("📢 Handling deleted incident from WebSocket broadcast"),(t=window.NotificationManager)==null||t.showIncidentNotification("Incident Deleted",e,"deleted"),e&&e.id&&(this.incidents=this.incidents.filter(a=>a.id!==e.id),this.applyFilters(),this.updateStats(),console.log("✅ Incident removed from local data"))}goToPage(e){this.currentPage=e,this.renderTable(),window.scrollTo({top:0,behavior:"smooth"})}showError(e){const t=document.createElement("div");t.className="fixed top-4 right-4 bg-red-500 text-white px-4 py-2 rounded-lg shadow-lg z-50",t.textContent=e,document.body.appendChild(t),setTimeout(()=>{document.body.removeChild(t)},3e3)}}function _(){if(typeof window.crimePageManager<"u"){console.log("ℹ️ Crime Page Manager already initialized, skipping...");return}try{console.log("📋 Initializing Crime Page Manager..."),window.crimePageManager=new I,console.log("✅ Crime Page Manager initialized successfully")}catch(w){console.error("❌ Failed to initialize Crime Page Manager:",w)}}document.readyState==="loading"?document.addEventListener("DOMContentLoaded",_):_();
