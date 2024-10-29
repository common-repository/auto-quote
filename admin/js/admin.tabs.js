function openTab(evt, tabName) {
  var i, tabcontent, tablinks;
  tabcontent = document.getElementsByClassName("tabcontent");
  for (i = 0; i < tabcontent.length; i++) {tabcontent[i].style.display = "none";}
  tablinks = document.getElementsByClassName("nav-tab");
  for (i = 0; i < tablinks.length; i++) {tablinks[i].className = tablinks[i].className.replace(" nav-tab-active", "");}
  document.getElementById(tabName).style.display = "block";
  evt.currentTarget.className += " nav-tab-active";
}