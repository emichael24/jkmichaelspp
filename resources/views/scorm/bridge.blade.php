<script>
(() => {
  const state = @json($state), channel = @json($channel), targetOrigin = @json($targetOrigin);
  const aliases = {'cmi.completion_status':'cmi.core.lesson_status', 'cmi.success_status':'cmi.core.lesson_status', 'cmi.location':'cmi.core.lesson_location', 'cmi.score.raw':'cmi.core.score.raw'};
  let lastError = '0';
  function get(key) { return String(state[aliases[key] || key] ?? ''); }
  function set(key,value) { key = aliases[key] || key; if (String(value).length > 64000) { lastError = '405'; return 'false'; } state[key] = String(value); return 'true'; }
  function commit() { window.parent.postMessage({type:'jk-scorm-commit', channel, state}, targetOrigin); return 'true'; }
  window.API = {LMSInitialize:()=> 'true', LMSGetValue:get, LMSSetValue:set, LMSCommit:commit, LMSFinish:commit, LMSGetLastError:()=>lastError, LMSGetErrorString:()=>lastError === '0' ? 'No error' : 'Value exceeds limit', LMSGetDiagnostic:()=>lastError};
  window.API_1484_11 = {Initialize:()=> 'true', GetValue:get, SetValue:set, Commit:commit, Terminate:commit, GetLastError:()=>lastError, GetErrorString:()=>lastError, GetDiagnostic:()=>lastError};
})();
</script>
