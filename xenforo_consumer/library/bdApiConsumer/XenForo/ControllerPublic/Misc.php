<?php

class bdApiConsumer_XenForo_ControllerPublic_Misc extends XFCP_bdApiConsumer_XenForo_ControllerPublic_Misc
{
	public function actionApiConsumerCallback()
	{
		return $this->responseReroute('bdApiConsumer_ControllerPublic_Callback', 'index');
	}

}
