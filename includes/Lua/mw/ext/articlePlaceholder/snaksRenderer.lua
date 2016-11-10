--[[
	@license GNU GPL v2+
	@author Lucie-Aimée Kaffee
	@author Marius Hoch
]]

local snaksRenderer = {}

-- Renders a given table of snaks.
--
-- @param table snaks
-- @return string wikitext
snaksRenderer.render = function( snaks )
  local result = ''
  if snaks ~= nil and type( snaks ) == 'table' then
    result = mw.wikibase.formatValues( snaks )
  end
  return result
end

return snaksRenderer
